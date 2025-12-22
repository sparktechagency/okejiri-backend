<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class FlutterwavePaymentController extends Controller
{
    use ApiResponse;
    public function getBanks()
    {
        $response = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
            ->get("https://api.flutterwave.com/v3/banks/NG");

        return response()->json($response->json());
    }

    public function createSubAccount(Request $request)
    {
        $provider = Auth::user();
        $request->validate([
            'account_number' => 'required|string',
            'bank_code'      => 'required|string',
        ]);
        $response = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
            ->post('https://api.flutterwave.com/v3/subaccounts', [
                "account_bank"   => $request->bank_code,
                "account_number" => $request->account_number,
                "business_name"  => $provider->name,
                "business_email" => $provider->email,
                "country"        => "NG",
                "split_type"     => "percentage",
                "split_value"    => 0.1,
            ]);

        $result = $response->json();

        if ($result['status'] === 'success') {
            $provider->sub_account_id = $result['data']['subaccount_id'];
            $provider->flutter_numeric_id = $result['data']['id'];
            $provider->save();

            return response()->json(['status' => 'success', 'message' => 'Subaccount created successfully', 'data' => $result]);

        }

        return response()->json(['status' => 'error', 'message' => 'Subaccount creation failed', 'error' => $result]);
    }

























    public function verifyPayment(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|string',
        ]);
        $transactionId = $request->transaction_id;

        $response = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
            ->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify");

        return $result = $response->json();

        if ($result['status'] === 'success' && $result['data']['status'] === 'successful') {

            $amountPaid = $result['data']['amount'];

            // হিসাব নিকাশ (Commission: 10%)
            $commission   = $amountPaid * 0.10;
            $vendorAmount = $amountPaid - $commission;

            // ডাটাবেসে সেভ করা (টাকা এখন আপনার কাছে HOLD অবস্থায় আছে)
            DB::table('transactions')->insert([
                'user_id'        => $request->user_id, // কাস্টমার
                'vendor_id'      => $vendorId,
                'transaction_id' => $result['data']['id'],
                'tx_ref'         => $result['data']['tx_ref'],
                'amount'         => $amountPaid,
                'vendor_amount'  => $vendorAmount,
                'commission'     => $commission,
                'type'           => 'payment',
                'status'         => 'successful', // টাকা সফলভাবে জমা হয়েছে
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            return response()->json(['status' => 'success', 'message' => 'Payment Verified & Held']);
        }

        return response()->json(['status' => 'error', 'message' => 'Verification failed'], 400);
    }

    // ==========================================
    // ৩. ভেন্ডরকে টাকা পাঠানো (Order Complete)
    // ==========================================
    public function releaseToVendor(Request $request)
    {
        // যেই ট্রানজেকশনের টাকা রিলিজ করবেন তার tx_ref বা transaction_id লাগবে
        $transaction = DB::table('transactions')->where('transaction_id', $request->transaction_id)->first();

        if (! $transaction || $transaction->status == 'paid_to_vendor') {
            return response()->json(['status' => 'error', 'message' => 'Invalid or already paid']);
        }

        // ভেন্ডরের ব্যাংক ডিটেইলস আনা
        $vendor = User::find($transaction->vendor_id);

        if (! $vendor->bank_code || ! $vendor->account_number) {
            return response()->json(['status' => 'error', 'message' => 'Vendor bank details missing']);
        }

        // ভেন্ডরের একাউন্টে টাকা পাঠানো (Transfer)
        $response = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
            ->post('https://api.flutterwave.com/v3/transfers', [
                "account_bank"   => $vendor->bank_code,
                "account_number" => $vendor->account_number,
                "amount"         => $transaction->vendor_amount, // কমিশন কেটে বাকি টাকা
                "narration"      => "Payment for Order",
                "currency"       => "NGN",
                "reference"      => "PAYOUT_" . time(),
                "debit_currency" => "NGN",
            ]);

        $result = $response->json();

        if ($result['status'] === 'success') {
            // স্ট্যাটাস আপডেট
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update(['status' => 'paid_to_vendor']);

            return response()->json(['status' => 'success', 'message' => 'Money sent to vendor', 'data' => $result]);
        }

        return response()->json(['status' => 'error', 'message' => 'Transfer failed', 'error' => $result]);
    }

    // ==========================================
    // ৪. টাকা ফেরত দেওয়া (Refund - Order Cancel)
    // ==========================================
    public function refundCustomer(Request $request)
    {
        $transactionId = $request->transaction_id;

        $response = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
            ->post("https://api.flutterwave.com/v3/transactions/{$transactionId}/refund");

        $result = $response->json();

        if ($result['status'] === 'success') {

            DB::table('transactions')
                ->where('transaction_id', $transactionId)
                ->update(['status' => 'refunded']);

            return response()->json(['status' => 'success', 'message' => 'Refund successful', 'data' => $result]);
        }

        return response()->json(['status' => 'error', 'message' => 'Refund failed', 'error' => $result]);
    }
}
