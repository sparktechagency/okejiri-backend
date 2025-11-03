<?php
namespace App\Http\Controllers\Api\Stripe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stripe\Connect\CreateAccountRequest;
use App\Http\Requests\Stripe\Connect\CreateInstantPayoutRequest;
use App\Http\Requests\Stripe\Connect\CreatePaymentIntentRequest;
use App\Http\Requests\Stripe\Connect\CreatePaymentLinkRequest;
use App\Http\Requests\Stripe\Connect\CreateTransferRequest;
use App\Models\Product;
use App\Models\User;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Balance;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Payout;
use Stripe\Stripe;
use Stripe\Transfer;

class ConnectController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        Stripe::setApiVersion(config('services.stripe.version'));
    }

    // Create Connected Account
    public function createAccount(CreateAccountRequest $request)
    {
        try {
            $user = Auth::user();
            if ($user->stripe_account_id) {
                return $this->responseError(null, 'You already have a Stripe account. Use the existing one.', 422);
            }
            $account = Account::create([
                'type'          => 'express',
                'country'       => $request->country,
                'email'         => $user->email,
                'capabilities'  => [
                    // 'card_payments' => ['requested' => true],
                    'transfers'     => ['requested' => true],
                ],
                'business_type' => 'individual',
                'tos_acceptance' => ['service_agreement' => 'recipient']
            ]);

            $user->update([
                'stripe_account_id'      => $account->id,
                'stripe_charges_enabled' => false,
                'stripe_payouts_enabled' => false,
            ]);

            $link = AccountLink::create([
                'account'     => $account->id,
                'refresh_url' => $request->refresh_url,
                'return_url'  => $request->return_url,
                'type'        => 'account_onboarding',
            ]);

            $data = [
                'account_id'     => $account->id,
                'onboarding_url' => $link->url,
            ];

            return $this->responseSuccess($data, 'Stripe account and onboarding link created successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    // Create PaymentIntent
    public function createPaymentIntent(CreatePaymentIntentRequest $request)
    {
        $seller = User::findOrFail($request->seller_id);
        if (! $seller->stripe_account_id) {
            return $this->responseError(null, "This seller doesn't have any stripe account.");
        }
        if (! $seller->stripe_charges_enabled) {
            return $this->responseError(null, 'The seller is currently unable to accept payments. Please choose another seller or try later.');
        }
        $buyer = Auth::user();
        try {
            $intent = PaymentIntent::create([
                'amount'               => $request->amount * 100,
                'currency'             => strtolower($request->currency),
                'payment_method_types' => ['card'],
                'metadata'             => [
                    'buyer_id'   => $buyer->id,
                    'seller_id'  => $request->input('seller_id'),
                    'product_id' => $request->input('product_id'),
                ],
            ]);
            return $this->responseSuccess($intent, 'PaymentIntent created successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    // Create PaymentLink
    public function createPaymentLink(CreatePaymentLinkRequest $request)
    {
        $seller = User::findOrFail($request->seller_id);
        if (! $seller->stripe_account_id) {
            return $this->responseError(null, "This seller doesn't have any stripe account.");
        }
        if (! $seller->stripe_charges_enabled) {
            return $this->responseError(null, 'The seller is currently unable to accept payments. Please choose another seller or try later.');
        }
        $product = Product::findOrFail($request->product_id);
        if (empty($product)) {
            return $this->responseError(null, "Product not found.", 404);
        }
        $buyer = Auth::user();
        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => strtolower($request->currency),
                        'unit_amount'  => $request->amount * 100,
                        'product_data' => [
                            'name'        => $product->name,
                            'description' => $product->description,
                        ],
                    ],
                    'quantity'   => $request->input('quantity', 1),
                ]],
                'mode'                 => 'payment',
                'payment_intent_data'  => [
                    'metadata' => [
                        'buyer_id'   => $buyer->id,
                        'seller_id'  => $request->input('seller_id'),
                        'product_id' => $request->input('product_id'),
                    ],
                ],
                'success_url'          => $request->input('success_url'),
                'cancel_url'           => $request->input('cancel_url'),
            ]);

            return $this->responseSuccess($session, 'PaymentLink created successfully. Please proceed to payment.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    // Create Login Link (Connected Account Dashboard Access)
    public function createLoginLink(Request $request)
    {
        try {
            $user = Auth::user();
            if (! $user->stripe_account_id) {
                return $this->responseError(null, 'No Stripe account found. Create an account first.', 422);
            }

            $loginLink = Account::createLoginLink($user->stripe_account_id);

            return $this->responseSuccess($loginLink, 'Stripe login link created successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    // Balance Retrieve (Platform or Connected Account)
    public function getBalance(Request $request)
    {
        try {
            $accountId = $request->query('account_id');
            $params    = [];

            if ($accountId) {
                $params['stripe_account'] = $accountId;
            }

            $balance = Balance::retrieve($params);
            return $this->responseSuccess($balance, 'Stripe balance retrieved successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }

    }

    // Transfer Create (Platform to Connected Account)
    public function createTransfer(CreateTransferRequest $request)
    {
        try {
            $amount       = $request->amount;
            $platform_fee = 0;
            $netAmount    = $amount - $platform_fee;
            $transfer     = Transfer::create([
                'amount'      => $netAmount * 100,
                'currency'    => strtolower($request->currency),
                'destination' => $request->destination_account,
                'metadata'    => [

                ],
            ]);
            return $this->responseSuccess($transfer, 'Transfer created successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    // Create Instant Payout
    public function createInstantPayout(CreateInstantPayoutRequest $request)
    {
        $user = Auth::user();
        if (! $user->stripe_account_id) {
            return $this->responseError(null, 'No Stripe account found. Create an account first.', 422);
        }
        try {
            $payout = Payout::create([
                'amount'   => $request->amount * 100,
                'currency' => strtolower($request->currency),
                'method'   => $request->method,
                'metadata' => [

                ],
            ], [
                'stripe_account' => $user->stripe_account_id,
            ]);
            return $this->responseSuccess($payout, 'Payout created successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
}
