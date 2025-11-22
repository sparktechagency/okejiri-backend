<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payout\StoreWithdrawRequest;
use App\Mail\BoostingRequestRejectMail;
use App\Models\Payout as SystemPayout;
use App\Notifications\WithdrawRequestPendingNotification;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $withdrawRequest = SystemPayout::create([
                'provider_id' => Auth::id(),
                'amount'      => $request->amount,
                'currency'    => strtolower($request->currency),
                'status'      => 'Pending',
            ]);
            Auth::user()->notify(new WithdrawRequestPendingNotification($withdrawRequest->id));
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
            $payout_data = SystemPayout::with('provider:id,name,avatar,kyc_status,email,phone,address,stripe_account_id', 'provider.provider_services.service')->findOrFail($id);
            $data        = [
                'data'              => $payout_data,
                'available_balance' => $this->getStripeBalance($payout_data->provider->stripe_account_id),
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
            if (! $system_payout->provider) {
                return $this->responseError(null, 'No provider information found.', 422);
            }

            if (! $system_payout->provider->stripe_account_id) {
                return $this->responseError(null, 'No Stripe account connected.', 422);
            }

            if (! $system_payout->provider->stripe_payouts_enabled) {
                return $this->responseError(null, 'Stripe payouts are not enabled for this account.', 422);
            }

            // $payout = Payout::create([
            //     'amount'   => $system_payout->amount * 100,
            //     'currency' => strtolower($system_payout->currency),
            //     'method'   => 'standard', #standard,instant
            // ], [
            //     'stripe_account' => $system_payout->provider->stripe_account_id,
            // ]);

            $system_payout->status = 'Successful';
            $system_payout->save();
            return $this->responseSuccess($system_payout, 'Payouts request accepted successfully.');
        } catch (Exception $e) {
            return $this->responseError(null, $e->getMessage());
        }
    }

    public function bulkPayoutAcceptReject(Request $request)
    {
        $request->validate([
            'ids'    => 'required|array|min:2',
            'ids.*'  => 'integer|exists:payouts,id',
            'type'   => 'required|in:accepted,rejected',
            'reason' => 'required_if:type,rejected',
        ]);
        try {
            $system_payouts = SystemPayout::with('provider')->whereIn('id', $request->ids)->get();
            foreach ($system_payouts as $payout) {
                if ($request->type == 'accepted') {
                    // Payout::create([
                    //     'amount'   => $payout->amount * 100,
                    //     'currency' => strtolower($payout->currency),
                    //     'method'   => 'standard', #standard,instant
                    // ], [
                    //     'stripe_account' => $payout->provider->stripe_account_id,
                    // ]);
                    $payout->status = 'Successful';
                }
                if ($request->type == 'rejected') {
                    $reason  = $request->input('reason');
                    $subject = 'Payout Request Rejected';
                    Mail::to($payout->provider->email)->send(new BoostingRequestRejectMail($payout->provider->name, $reason, 'rejected', $subject, 'payout'));
                    $payout->status = 'Rejected';
                }
                $payout->save();
            }
            return $this->responseSuccess($system_payouts, "Bulk payouts request $request->type successfully.");
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
