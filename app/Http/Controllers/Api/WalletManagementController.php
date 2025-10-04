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

class WalletManagementController extends Controller
{
    use ApiResponse;

    public function depositSuccess(DepositStoreRequest $request)
    {
        $transaction                   = new Transaction();
        $transaction->sender_id        = Auth::id();
        $transaction->amount           = $request->deposit_amount;
        $transaction->transaction_type = 'deposit';
        $transaction->save();
        return $this->responseSuccess($transaction, 'Account Deposit Successfully.');
    }

    public function transferBalance(TransferRequest $request)
    {
        $sender   = Auth::user();
        $receiver = User::where('wallet_address', $request->wallet_address)->first();
        $amount   = $request->amount;

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

        $transactions = Transaction::with(['sender:id,name,kyc_status','receiver:id,name,kyc_status', 'package:id,title'])
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
                    'deposit'  => 'credit',
                    default    => null,
                };

                $data              = $transaction->toArray();
                $data['direction'] = $direction;

                return $data;
            });

        return $this->responseSuccess($transactions, 'My transactions retrieved successfully.');
    }

}
