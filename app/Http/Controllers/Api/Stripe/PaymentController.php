<?php
namespace App\Http\Controllers\Api\Stripe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stripe\Payment\CreatePaymentIntentRequest;
use App\Http\Requests\Stripe\Payment\CreatePaymentLinkRequest;
use App\Models\Product;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Support\Facades\Auth;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentController extends Controller
{
    use ApiResponse;
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        Stripe::setApiVersion(config('services.stripe.version'));
    }

    // Create PaymentIntent
    public function createPaymentIntent(CreatePaymentIntentRequest $request)
    {
        $buyer = Auth::user();
        try {
            $intent = PaymentIntent::create([
                'amount'               => $request->amount * 100,
                'currency'             => strtolower($request->currency),
                'payment_method_types' => ['card'],
                'metadata'             => [
                    'buyer_id'   => $buyer->id,
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
}
