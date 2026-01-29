<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payout\StoreWithdrawRequest;
use App\Mail\BoostingRequestRejectMail;
use App\Models\Payout as SystemPayout;
use App\Models\Transaction;
use App\Notifications\WithdrawRequestPendingNotification;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Stripe\Balance;
use Stripe\Stripe;

class PayoutController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        // Stripe::setApiVersion(config('services.stripe.version'));
    }
    public function withdrawRequest(StoreWithdrawRequest $request)
    {
        try {
            $provider = Auth::user();
            if ($provider->wallet_balance < $request->amount) {
                return $this->responseError(null, 'Insufficient wallet balance.', 422);
            }
            $withdrawRequest = SystemPayout::create([
                'provider_id' => Auth::id(),
                'amount'      => $request->amount,
                'currency'    => strtolower($request->currency),
                'status'      => 'Pending',
            ]);

            Auth::user()->notify(new WithdrawRequestPendingNotification(
                'Withdrawal request in Pending',
                'After approving from the admin side you will get your money.',
                [
                    'payout_id' => $withdrawRequest->id,
                    'type'      => 'payout_request',
                ]
            ));
            return $this->responseSuccess($withdrawRequest, 'Withdraw request sending successfully.', 201);
        } catch (Exception $e) {
            return $this->responseError(null, $e->getMessage());
        }
    }

    public function payoutRequests(Request $request)
    {
        $request->validate([
            'filter' => 'nullable|in:Pending,Successful,Rejected',
        ]);
        $per_page = $request->input('per_page', 10);
        $search   = $request->input('search');
        $filter   = $request->input('filter');

        $data = SystemPayout::with('provider:id,name,avatar,kyc_status')
            ->when($search, function ($query) use ($search) {
                $query->whereHas('provider', function ($q) use ($search) {
                    $q->where('name', 'LIKE', '%' . $search . '%');
                });
            })
            ->when($filter, function ($query) use ($filter) {
                $query->where('status', $filter);
            })
            ->latest('id')
            ->paginate($per_page);

        return $this->responseSuccess($data, 'All payout request retrieved successfully');
    }
    public function payoutRequestsDetails($id)
    {
        try {
            $payout_data = SystemPayout::with('provider:id,name,avatar,kyc_status,email,phone,address,sub_account_id', 'provider.provider_services.service')->findOrFail($id);
            $data        = [
                'data'              => $payout_data,
                'available_balance' => $this->getStripeBalance($payout_data->provider->sub_account_id),
            ];
            return $this->responseSuccess($data, 'Payout request detail retrieved successfully');
        } catch (Exception $e) {
            return $this->responseError(null, $e->getMessage());
        }
    }

    public function previousPayouts($provider_id)
    {
        $previousPayouts = SystemPayout::where('provider_id', $provider_id)->latest('id')->get();
        return $this->responseSuccess($previousPayouts, 'Previous payouts request retrieved successfully');
    }
    public function payoutRejected(Request $request, $id)
    {
        try {
            $payout         = SystemPayout::with('provider')->findOrFail($id);
            $payout->status = 'Rejected';
            $payout->save();
            $reason  = $request->input('reason');
            $subject = 'Payout Request Rejected';
            Mail::to($payout->provider->email)->send(new BoostingRequestRejectMail($payout->provider->name, $reason, 'rejected', $subject, 'payout'));
            return $this->responseSuccess($payout, 'Payouts request rejected successfully.');
        } catch (Exception $e) {
            return $this->responseError(null, $e->getMessage());
        }
    }
    public function payoutAccepted($id)
    {

        try {
            $system_payout = SystemPayout::with('provider')->findOrFail($id);
            $response      = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                ->get('https://api.flutterwave.com/v3/transfers/fee', [
                    'amount'   => $system_payout->amount,
                    'currency' => 'NGN',
                    'type'     => 'account',
                ]);

            $result = $response->json();
            if ($result['status'] === 'success') {
                $fee = $result['data'][0]['fee'];
            } else {
                $fee = 53.75;
            }
            $amount           = $system_payout->amount;
            $amountToWithdraw = $amount - $fee;
            if ($system_payout->provider->wallet_balance < $system_payout->amount) {
                return $this->responseError(null, 'Provider has insufficient wallet balance.', 422);
            }
            if ($system_payout->status != 'Pending') {
                return $this->responseError(null, 'This payout request has already been processed.', 422);
            }
            if (! $system_payout->provider) {
                return $this->responseError(null, 'No provider information found.', 422);
            }

            if (! $system_payout->provider->sub_account_id) {
                return $this->responseError(null, 'No sub account information found.', 422);
            }

            $balanceResponse = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                ->get('https://api.flutterwave.com/v3/balances');

            $balanceData = $balanceResponse->json();
            if ($balanceData['status'] === 'success') {
                $availableBalance = $balanceData['data'][0]['available_balance'];

                if ($availableBalance < $amount) {
                    return $this->responseError(null, 'Merchant account has insufficient balance to process this payout.', 400);
                }
            } else {
                return $this->responseError(null, 'Failed to retrieve merchant balance.', 500);
            }

            $flutter_numeric_id = $system_payout->provider->flutter_numeric_id;

            $fetchResponse = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                ->get("https://api.flutterwave.com/v3/subaccounts/{$flutter_numeric_id}");

            $subData = $fetchResponse->json();

            if ($subData['status'] !== 'success') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Failed to fetch bank details using ID: ' . $flutter_numeric_id,
                ]);
            }
            $bankCode      = $subData['data']['account_bank'];
            $accountNumber = $subData['data']['account_number'];
            $vendorName    = $subData['data']['business_name'];

            $transferResponse = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                ->post('https://api.flutterwave.com/v3/transfers', [
                    "account_bank"   => $bankCode,
                    "account_number" => $accountNumber,
                    "amount"         => $amountToWithdraw,
                    "narration"      => "Payout to " . $vendorName,
                    "currency"       => "NGN",
                    "reference"      => "PAY_" . time() . '_' . uniqid(),
                    "debit_currency" => "NGN",
                ]);

            $result = $transferResponse->json();
            if ($result['status'] === 'success') {

                $system_payout->provider->wallet_balance -= $amount;
                $system_payout->provider->save();

                $system_payout->status = 'Successful';
                $system_payout->save();
                $transaction = Transaction::create([
                    'sender_id'        => $system_payout->provider->id,
                    'amount'           => $amount,
                    'transaction_type' => 'withdraw',
                ]);
                return $this->responseSuccess($system_payout, 'Payouts request accepted successfully.');
            } else {
                return $this->responseError(null, 'Transfer failed: ' . ($result['message'] ?? 'Unknown error'), 500);
            }
        } catch (Exception $e) {
            return $this->responseError(null, $e->getMessage());
        }
    }

    public function bulkPayoutAcceptReject(Request $request)
    {
        $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:payouts,id',
            'type'   => 'required|in:accepted,rejected',
            'reason' => 'required_if:type,rejected',
        ]);

        $processedCount = 0;
        $failedCount    = 0;
        $errors         = [];

        try {
            $system_payouts = SystemPayout::with('provider')->whereIn('id', $request->ids)->get();

            foreach ($system_payouts as $payout) {

                if ($payout->status != 'Pending') {
                    $failedCount++;
                    $errors[] = "ID {$payout->id}: Already processed.";
                    continue;
                }

                if ($request->type == 'rejected') {
                    $payout->status = 'Rejected';
                    $payout->save();

                    $reason  = $request->input('reason');
                    $subject = 'Payout Request Rejected';

                    if ($payout->provider->email) {
                        Mail::to($payout->provider->email)->send(new BoostingRequestRejectMail($payout->provider->name, $reason, 'rejected', $subject, 'payout'));
                    }

                    $processedCount++;
                    continue;
                }

                if ($request->type == 'accepted') {

                    if (! $payout->provider || ! $payout->provider->sub_account_id) {
                        $failedCount++;
                        $errors[] = "ID {$payout->id}: Provider or Subaccount missing.";
                        continue;
                    }

                    $response = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                        ->get('https://api.flutterwave.com/v3/transfers/fee', [
                            'amount'   => $payout->amount,
                            'currency' => 'NGN',
                            'type'     => 'account',
                        ]);

                    $result = $response->json();

                    if (isset($result['status']) && $result['status'] === 'success') {
                        $fee = $result['data'][0]['fee'];
                    } else {
                        $fee = 53.75;
                    }

                    $originalAmount   = $payout->amount;
                    $amountToTransfer = $originalAmount - $fee;

                    if ($payout->provider->wallet_balance < $originalAmount) {
                        $failedCount++;
                        $errors[] = "ID {$payout->id}: Insufficient wallet balance.";
                        continue;
                    }

                    $balanceResponse = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))->get('https://api.flutterwave.com/v3/balances');

                    if ($balanceResponse->failed()) {
                        $failedCount++;
                        $errors[] = "ID {$payout->id}: Admin balance check failed.";
                        continue;
                    }

                    $availBalance = collect($balanceResponse->json()['data'])->firstWhere('currency', 'NGN')['available_balance'] ?? 0;

                    if ($availBalance < $amountToTransfer) {
                        $failedCount++;
                        $errors[] = "ID {$payout->id}: Admin has insufficient FW balance.";
                        continue;
                    }

                    $flutter_numeric_id = $payout->provider->flutter_numeric_id;
                    $fetchResponse      = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                        ->get("https://api.flutterwave.com/v3/subaccounts/{$flutter_numeric_id}");

                    if ($fetchResponse->failed()) {
                        $failedCount++;
                        $errors[] = "ID {$payout->id}: Subaccount fetch failed.";
                        continue;
                    }

                    $subData       = $fetchResponse->json();
                    $bankCode      = $subData['data']['account_bank'];
                    $accountNumber = $subData['data']['account_number'];
                    $vendorName    = $subData['data']['business_name'];

                    $transferResponse = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                        ->post('https://api.flutterwave.com/v3/transfers', [
                            "account_bank"   => $bankCode,
                            "account_number" => $accountNumber,
                            "amount"         => $amountToTransfer,
                            "narration"      => "Payout to " . $vendorName,
                            "currency"       => "NGN",
                            "reference"      => "PAY_" . time() . '_' . uniqid(),
                            "debit_currency" => "NGN",
                        ]);

                    $transResult = $transferResponse->json();

                    if (isset($transResult['status']) && $transResult['status'] === 'success') {

                        $payout->provider->decrement('wallet_balance', $originalAmount);

                        $payout->status = 'Successful';
                        $payout->save();

                        Transaction::create([
                            'sender_id'        => $payout->provider->id,
                            'amount'           => $originalAmount,
                            'transaction_type' => 'withdraw',
                            'description'      => 'Payout via Flutterwave (Fee Deducted)',
                        ]);

                        $processedCount++;
                    } else {
                        $failedCount++;
                        $msg      = $transResult['message'] ?? 'Transfer Failed';
                        $errors[] = "ID {$payout->id}: $msg";
                    }
                }
            }
            return response()->json([
                'status'  => 'success',
                'message' => "Bulk process completed. Success: $processedCount, Failed: $failedCount",
                'data'    => [
                    'processed_count' => $processedCount,
                    'failed_count'    => $failedCount,
                    'errors'          => $errors,
                ],
            ]);

        } catch (Exception $e) {
            return $this->responseError(null, $e->getMessage());
        }
    }

    private function getStripeBalance($accountId)
    {
        if ($accountId) {
            $params['stripe_account'] = $accountId;
        }
        $balance = Balance::retrieve($params);
        return $balance;
    }

}
