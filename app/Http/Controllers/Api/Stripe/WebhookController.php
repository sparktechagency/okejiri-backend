<?php
namespace App\Http\Controllers\Api\Stripe;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;
use UnexpectedValueException;

class WebhookController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }
    public function handleWebhook(Request $request)
    {
        $payload        = $request->getContent();
        $sigHeader      = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (UnexpectedValueException $e) {
            return response()->json(['message' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        switch ($event->type) {

            case 'account.updated':
                $accountData = $event->data->object;
                $user        = User::where('stripe_account_id', $accountData->id)->first();
                if ($user) {
                    $user->stripe_charges_enabled = $accountData->charges_enabled;
                    $user->stripe_payouts_enabled = $accountData->payouts_enabled;
                    $user->save();
                }
                // Log::info('account.updated: ' .$accountData);
                break;

            case 'payment_intent.created':
                $paymentIntent = $event->data->object;
                //   Log::info('payment_intent.created: '.$paymentIntent);
                break;

            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                //   Log::info('payment_intent.succeeded: '.$paymentIntent);
                break;

            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                // Log::info('payment_intent.payment_failed: ' .$paymentIntent);
                break;

            case 'payment_intent.canceled':
                $paymentIntent = $event->data->object;
                // Log::info('payment_intent.canceled: '.$paymentIntent);
                break;

            case 'checkout.session.completed':
                $paymentIntent = $event->data->object;
                // Log::info('checkout.session.completed: ' . $paymentIntent);
                break;

            case 'transfer.created':
                $transfer = $event->data->object;
                // Log::info('transfer.created: ' . $transfer);
                break;

            case 'transfer.failed':
                $transfer = $event->data->object;
                // Log::info('transfer.failed: ' . $transfer);
                break;

            case 'transfer.paid':
                $transfer = $event->data->object;
                // Log::info('transfer.paid: ' . $transfer);
                break;

            case 'payout.created':
                $payout = $event->data->object;
                // Log::info('payout.created: ' . $payout);
                break;

            case 'payout.paid':
                $payout = $event->data->object;
                // Log::info('payout.paid: ' . $payout);
                break;

            case 'payout.failed':
                $payout = $event->data->object;
                // Log::info('payout.failed: ' . $payout);
                break;

            default:
                Log::info('Unhandled Stripe event: ' . $event->type);
                break;
        }
        return response()->json(['status' => 'success']);
    }
}
