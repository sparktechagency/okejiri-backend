<?php
namespace App\Http\Controllers\Api\Stripe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stripe\Payment\CreatePaymentIntentRequest;
use App\Traits\ApiResponse;
use Exception;
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

    public function createPaymentIntent(CreatePaymentIntentRequest $request)
    {
        try {
            $intent = PaymentIntent::create([
                'amount'                    => $request->amount * 100,
                'currency'                  => strtolower($request->currency),
                'payment_method_types' => ['card'],
                // 'automatic_payment_methods' => [
                //     'enabled'         => true,
                //     'allow_redirects' => 'never',
                // ],
                // 'payment_method'            => 'pm_card_visa',
                // 'confirm'                   => true,
                // 'metadata'                  => [

                // ],
            ]);
            return $this->responseSuccess($intent, 'PaymentIntent created successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

}
