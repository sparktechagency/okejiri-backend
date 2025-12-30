<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\DepositStoreRequest;
use App\Http\Requests\Wallet\TransferRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WalletManagementController extends Controller
{
    use ApiResponse;

    public function depositSuccess(DepositStoreRequest $request)
    {
        $transactionId = $request->payment_intent_id;
        $response      = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
            ->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify");
     return   $result = $response->json();

        if ($result['status'] === 'success' && $result['data']['status'] === 'successful') {
            $amountPaid                    = $result['data']['amount'];
            $transaction                   = new Transaction();
            $transaction->sender_id        = Auth::id();
            $transaction->amount           = $amountPaid;
            $transaction->transaction_type = 'deposit';
            $transaction->save();
            $user = Auth::user();
            $user->wallet_balance += $amountPaid;
            $user->save();
            return $this->responseSuccess($transaction, 'Account Deposit Successfully.');
        } else {
            return $this->responseError(null, 'Payment verification failed.', 400);
        }
    }

    public function transferBalance(TransferRequest $request)
    {
        $sender   = Auth::user();
        $receiver = User::where('wallet_address', $request->wallet_address)->first();
        $amount   = $request->amount;
        if ($sender->role === 'PROVIDER' && $request->account_type === 'wallet_balance') {
            return $this->responseError(null, 'This account type is not allowed for transfer wallet balance.', 403);
        }
        if (! $receiver) {
            return $this->responseError(null, 'Invalid wallet address.', 404);
        }
        if ($receiver->id === $sender->id) {
            return $this->responseError(null, 'You cannot transfer to your own wallet.', 400);
        }
        if ($sender->role !== $receiver->role) {
            return $this->responseError(null, 'You cannot transfer to a different type account.', 400);
        }
        if ($request->account_type === 'wallet_balance' && $sender->wallet_balance < $amount) {
            return $this->responseError(null, 'Insufficient wallet balance.', 400);
        }
        if ($request->account_type === 'referral_balance' && $sender->referral_balance < $amount) {
            return $this->responseError(null, 'Insufficient referral balance.', 400);
        }

        DB::beginTransaction();
        try {
            if ($request->account_type === 'wallet_balance') {
                $sender->wallet_balance -= $amount;
                $receiver->wallet_balance += $amount;
            } elseif ($request->account_type === 'referral_balance') {
                $sender->referral_balance -= $amount;
                $receiver->referral_balance += $amount;
            }
            $sender->save();
            $receiver->save();

            $transaction = Transaction::create([
                'sender_id'        => $sender->id,
                'receiver_id'      => $receiver->id,
                'amount'           => $amount,
                'profit'           => 0,
                'transaction_type' => 'transfer',
            ]);

            DB::commit();
            return $this->responseSuccess($transaction, 'Balance transferred successfully.');

        } catch (Exception $e) {
            DB::rollBack();
            return $this->responseError($e->getMessage(), 'Something went wrong', 500);
        }
    }

    public function myTransactions(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $userId  = auth()->id();

        $transactions = Transaction::with(['sender:id,name,kyc_status', 'receiver:id,name,kyc_status', 'package:id,title'])
            ->where(function ($query) use ($userId) {
                $query->where('sender_id', $userId)
                    ->orWhere('receiver_id', $userId);
            })
            ->latest('id')
            ->paginate($perPage)
            ->through(function ($transaction) use ($userId) {
                $type      = $transaction->transaction_type;
                $direction = match ($type) {
                    'withdraw' => 'debit',
                    'purchase' => $transaction->receiver_id == $userId ? 'credit' : 'debit',
                    'transfer' => $transaction->sender_id == $userId ? 'debit' : 'credit',
                    'refund'   => 'credit',
                    'deposit'  => 'credit',
                    default    => null,
                };

                $data              = $transaction->toArray();
                $data['direction'] = $direction;

                return $data;
            });

        return $this->responseSuccess($transactions, 'My transactions retrieved successfully.');
    }

    public function transactions(Request $request)
    {
        $per_page     = $request->input('per_page', 10);
        $search       = $request->input('search');
        $transactions = Transaction::with('sender:id,name,avatar,kyc_status', 'receiver:id,name,avatar,kyc_status')->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('sender', function ($senderQuery) use ($search) {
                    $senderQuery->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('receiver', function ($receiverQuery) use ($search) {
                        $receiverQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhere('transactions.transaction_id', 'like', "%{$search}%")
                    ->orWhere('transactions.amount', 'like', "%{$search}%")
                    ->orWhere('transactions.profit', 'like', "%{$search}%");
            });
        })->latest('id')->where('transaction_type', 'purchase')->paginate($per_page);
        return $this->responseSuccess($transactions, 'Transactions retrieved successfully.');
    }
    public function userTransactions(Request $request, $user_id)
    {
        $per_page     = $request->input('per_page', 10);
        $transactions = Transaction::with('receiver:id,name,avatar,kyc_status')->latest('id')->where('sender_id', $user_id)->paginate($per_page);
        return $this->responseSuccess($transactions, 'User transactions retrieved successfully.');
    }
    public function providerTransactions(Request $request, $provider_id)
    {
        $per_page     = $request->input('per_page', 10);
        $transactions = Transaction::with('sender:id,name,avatar,kyc_status')->latest('id')->where('receiver_id', $provider_id)->paginate($per_page);
        return $this->responseSuccess($transactions, 'Provider transactions retrieved successfully.');
    }
}
