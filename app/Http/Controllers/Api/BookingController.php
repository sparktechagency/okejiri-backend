<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\BookingStoreRequest;
use App\Http\Requests\Rating\ExtendDeliveryStoreRequest;
use App\Models\AddToCart;
use App\Models\BillingDetail;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\ExtendDeliveryTime;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\DeliveryRequestAcceptDeclineRequest;
use App\Notifications\DeliveryRequestSentNotification;
use App\Notifications\ExtendDeliveryTimeAcceptNotification;
use App\Notifications\ExtendDeliveryTimeDeclineNotification;
use App\Notifications\ExtendDeliveryTimeNotification;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderApprovedNotification;
use App\Notifications\OrderRejectNotification;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class BookingController extends Controller
{
    use ApiResponse;

    public function getProviderDiscount(Request $request)
    {
        $request->validate([
            'provider_id' => 'required|numeric',
        ]);
        $provider = User::where('id', $request->provider_id)->first()->discount;
        return $this->responseSuccess($provider, 'Discount for group booking retrieved successfully.');
    }

    public function create(BookingStoreRequest $request)
    {
        $existInCart = AddToCart::where('user_id', Auth::id())->exists();

        if (! $existInCart) {
            return $this->responseError(null, 'Your cart is empty.');
        }

        DB::beginTransaction();
        try {

            $profit = Setting::findOrFail(1)->profit;

            if ($request->payment_type === 'from_balance') {
                $user = User::lockForUpdate()->findOrFail(Auth::id());

                if ($user->wallet_balance < $request->price) {
                    return $this->responseError(null, 'Insufficient wallet balance');
                }

                $user->wallet_balance -= $request->price;
                $user->save();
            }

            $booking = Booking::create([
                'user_id'            => Auth::id(),
                'provider_id'        => $request->provider_id,
                'package_id'         => null,
                'booking_process'    => $request->booking_process,
                'booking_type'       => $request->booking_type,
                'schedule_date'      => $request->schedule_date,
                'schedule_time_slot' => $request->schedule_time_slot,
                'price'              => $request->price,
                'number_of_people'   => $request->number_of_people,
                'payment_type'       => $request->payment_type,
                'payment_intent_id'  => $request->payment_intent_id,
                'order_id'           => 'ORD-' . Str::upper(Str::random(10)),
                'status'             => 'New',
            ]);

            BillingDetail::create([
                'booking_id' => $booking->id,
                'name'       => $request->name,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'location'   => $request->address,
            ]);

            Transaction::create([
                'booking_id'       => $booking->id,
                'amount'           => $request->price,
                'transaction_type' => 'purchase',
                'profit'           => ($request->price * $profit) / 100,
            ]);

            $cartItems = AddToCart::where('user_id', Auth::id())->get();

            foreach ($cartItems as $item) {
                BookingItem::create([
                    'package_id' => $item->package_id,
                    'booking_id' => $booking->id,
                ]);
                $item->delete();
            }
            $provider = User::findOrFail($request->provider_id);
            $user     = Auth::user()->only(['id', 'name', 'kyc_status']);
            DB::commit();
            $provider->notify(new NewOrderNotification($user, $booking->price, $booking->id));
            return $this->responseSuccess($booking, 'Booking completed successfully');

        } catch (Exception $e) {
            DB::rollBack();
            return $this->responseError($e->getMessage());
        }
    }

    public function getProviderOrders(Request $request)
    {
        $request->validate([
            'status'          => 'required|in:New,Pending,Completed',
            'booking_process' => 'required|in:instant,schedule',
        ]);
        $per_page        = $request->input('per_page', 10);
        $status          = $request->input('status');
        $booking_process = $request->input('booking_process');

        $bookings = Booking::with('user:id,name,avatar,kyc_status')
            ->withCount('booking_items')
            ->where('provider_id', Auth::id())
            ->where('booking_process', $booking_process)
            ->where('status', $status)
            ->latest('id')
            ->paginate($per_page);

        return $this->responseSuccess($bookings, 'Booking data retrieved successfully.');
    }

    public function orderDetails($order_id)
    {
        try {
            $order_details = Booking::with([
                'user:id,name,avatar,kyc_status',
                'provider' => function ($q) {
                    $q->select('id', 'name', 'avatar', 'kyc_status')
                        ->withAvg('ratings', 'rating')
                        ->withCount('ratings');
                },
                'billing',
                'booking_items.package.package_detail_items',
                'review.user:id,name,avatar',
            ])->findOrFail($order_id);

            if ($order_details->provider) {
                $avg                                         = $order_details->provider->ratings_avg_rating;
                $order_details->provider->ratings_avg_rating = number_format($avg ?? 0, 1);
            }
            return $this->responseSuccess($order_details, 'Order details retrieved successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function requestExtendDeliveryTime(ExtendDeliveryStoreRequest $request)
    {
        $booking       = Booking::findOrFail($request->booking_id);
        $user          = User::where('id', $booking->user_id)->first();
        $extendRequest = ExtendDeliveryTime::create([
            'booking_id' => $request->booking_id,
            'time'       => $request->time,
            'reason'     => $request->reason,
        ]);

        $provider = Auth::user()->only(['id', 'name', 'kyc_status']);
        $user->notify(new ExtendDeliveryTimeNotification($provider, $extendRequest->id));
        return $this->responseSuccess($extendRequest, 'Delivery time extension request submitted successfully.');
    }

    public function acceptExtendDeliveryTime($request_id)
    {
        $extendRequest = ExtendDeliveryTime::findOrFail($request_id);
        if ($extendRequest->status != 'Pending') {
            return $this->responseError(null, "Request is already $extendRequest->status");
        }
        $extendRequest->update([
            'status' => 'Accept',
        ]);

        $booking                      = Booking::findOrFail($extendRequest->booking_id);
        $booking->total_delivery_time = $booking->total_delivery_time + $extendRequest->time;
        $booking->save();

        $user     = User::findOrFail($booking->user_id)->only(['id', 'name', 'kyc_status']);
        $provider = User::findOrFail($booking->provider_id);
        $provider->notify(new ExtendDeliveryTimeAcceptNotification($user, $extendRequest->id));

        return $this->responseSuccess($extendRequest, 'Delivery time extension request accepted successfully.');
    }

    public function declineExtendDeliveryTime($request_id)
    {
        $extendRequest = ExtendDeliveryTime::findOrFail($request_id);
        if ($extendRequest->status != 'Pending') {
            return $this->responseError(null, "Request is already $extendRequest->status");
        }
        $extendRequest->update([
            'status' => 'Decline',
        ]);

        $booking  = Booking::findOrFail($extendRequest->booking_id);
        $user     = User::findOrFail($booking->user_id)->only(['id', 'name', 'kyc_status']);
        $provider = User::findOrFail($booking->provider_id);
        $provider->notify(new ExtendDeliveryTimeDeclineNotification($user, $extendRequest->id));

        return $this->responseSuccess($extendRequest, 'Delivery time extension request declined.');
    }

    public function orderApprove($booking_id)
    {
        try {
            $booking         = Booking::findOrFail($booking_id);
            $booking->status = 'Pending';
            $booking->save();

            $provider = User::findOrFail($booking->provider_id)->only(['id', 'name', 'kyc_status']);
            $user     = User::findOrFail($booking->user_id);
            $user->notify(new OrderApprovedNotification($provider, $booking->id));
            return $this->responseSuccess($booking, 'Order approved successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function orderReject($booking_id)
    {
        try {
            $booking = Booking::with('transaction')->findOrFail($booking_id);
            $user    = User::findOrFail($booking->user_id);
            if ($booking->payment_type == 'from_balance') {
                $user->wallet_balance += $booking->price;
                $user->save();
            } elseif ($booking->payment_type == 'make_payment') {
                Stripe::setApiKey(config('services.stripe.secret'));

                $intent   = PaymentIntent::retrieve($booking->payment_intent_id);
                $chargeId = $intent->latest_charge;

                if (! $chargeId) {
                    return $this->responseError(null, 'Could not find any charge information for this payment.');
                }
                $refund = Refund::create([
                    'charge' => $chargeId,
                ]);
                if ($booking->transaction) {
                    $booking->transaction->delete();
                }
            }
            $booking->status = 'Reject';
            $booking->save();
            $provider = User::findOrFail($booking->provider_id)->only(['id', 'name', 'kyc_status']);
            $user->notify(new OrderRejectNotification($provider, $booking->id));
            return $this->responseSuccess($booking, 'Order rejected successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function requestForDelivery($booking_id)
    {
        try {
            $booking  = Booking::findOrFail($booking_id);
            $provider = User::findOrFail($booking->provider_id)->only(['id', 'name', 'kyc_status']);
            $user     = User::findOrFail($booking->user_id);
            $user->notify(new DeliveryRequestSentNotification($provider, $booking->id));
            return $this->responseSuccess($booking, 'Delivery request sent successfully');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
    public function declineDeliveryRequest($booking_id)
    {
        try {
            $booking  = Booking::findOrFail($booking_id);
            $provider = User::findOrFail($booking->provider_id);

            $user = User::findOrFail($booking->user_id)->only(['id', 'name', 'kyc_status']);

            $notification_data = [
                'title'     => 'Delivery request decline',
                'sub_title' => 'Tap to see details',
                'user'      => $user,
                'order_id'  => $booking_id,
                'type'      => 'delivery_request_decline',
            ];
            $provider->notify(new DeliveryRequestAcceptDeclineRequest($notification_data));
            return $this->responseSuccess($booking, 'Delivery request decline successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
    public function acceptDeliveryRequest($booking_id)
    {
        try {
            return $booking  = Booking::findOrFail($booking_id);
            $booking->status = 'Completed';
            $booking->save();
            //rest code here

            $provider          = User::findOrFail($booking->provider_id);
            $user              = User::findOrFail($booking->user_id)->only(['id', 'name', 'kyc_status']);
            $notification_data = [
                'title'     => 'Delivery request approved',
                'sub_title' => 'Tap to see details',
                'user'      => $user,
                'order_id'  => $booking_id,
                'type'      => 'delivery_request_approved',
            ];
            $provider->notify(new DeliveryRequestAcceptDeclineRequest($notification_data));
            return $this->responseSuccess($booking, 'Delivery request accept successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
    public function myBookings(Request $request)
    {
        $per_page = $request->input('per_page', 10);

        $bookings = Booking::with([
            'provider' => function ($q) {
                $q->select('id', 'name', 'avatar', 'kyc_status', 'provider_type')
                    ->withAvg('ratings', 'rating');
            }, 'provider.company:id,provider_id,company_logo,company_name',
        ])
            ->where('user_id', Auth::id())
            ->withCount('booking_items')
            ->whereIn('status', ['New', 'Pending'])
            ->latest('id')
            ->paginate($per_page);

        $bookings->getCollection()->transform(function ($booking) {
            if ($booking->provider) {
                $avg                                   = $booking->provider->ratings_avg_rating;
                $booking->provider->ratings_avg_rating = number_format($avg ?? 0, 1);
            }
            return $booking;
        });

        return $this->responseSuccess($bookings, 'My bookings retrieved successfully.');
    }
    public function bookingsHistory(Request $request)
    {
        $per_page = $request->input('per_page', 10);

        $bookings = Booking::with([
            'provider' => function ($q) {
                $q->select('id', 'name', 'avatar', 'kyc_status', 'provider_type')
                    ->withAvg('ratings', 'rating');
            }, 'provider.company:id,provider_id,company_logo,company_name',
        ])
            ->where('user_id', Auth::id())
            ->withCount('booking_items')
            ->where('status', 'Completed')
            ->latest('id')
            ->paginate($per_page);

        $bookings->getCollection()->transform(function ($booking) {
            if ($booking->provider) {
                $avg                                   = $booking->provider->ratings_avg_rating;
                $booking->provider->ratings_avg_rating = number_format($avg ?? 0, 1);
            }
            return $booking;
        });

        return $this->responseSuccess($bookings, 'Bookings history retrieved successfully.');
    }

    public function orderCancel(Request $request, $order_id)
    {
        try {
            DB::beginTransaction();

            $booking = Booking::findOrFail($order_id);

            if ($booking->status == 'Cancelled') {
                return $this->responseError(null, 'Order is already cancelled.');
            }

            if ($booking->payment_type == 'from_balance') {
                $user = $booking->user;
                $user->wallet_balance += $booking->price;
                $user->save();
            } elseif ($booking->payment_type == 'make_payment') {
                Stripe::setApiKey(config('services.stripe.secret'));

                $intent   = PaymentIntent::retrieve($booking->payment_intent_id);
                $chargeId = $intent->latest_charge;

                if (! $chargeId) {
                    return $this->responseError(null, 'Could not find any charge information for this payment.');
                }

                $refund = Refund::create([
                    'charge' => $chargeId,
                ]);
            } else {
                return $this->responseError(null, 'Invalid payment type.');
            }

            $booking->status = 'Cancelled';
            $booking->save();

            DB::commit();

            return $this->responseSuccess($booking, 'Order cancelled successfully.');

        } catch (Exception $e) {
            DB::rollBack();
            return $this->responseError($e->getMessage());
        }

    }
}
