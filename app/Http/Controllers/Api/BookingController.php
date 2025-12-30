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
use App\Models\Message;
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
use App\Notifications\OrderCancelNotification;
use App\Notifications\OrderRejectNotification;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
                Transaction::create([
                    'booking_id'       => $booking->id,
                    'sender_id'        => $booking->user_id,
                    // 'receiver_id'      => $booking->provider_id,
                    'amount'           => $request->price,
                    'transaction_type' => 'purchase',
                    'profit'           => ($request->price * $profit) / 100,
                ]);
            } elseif ($request->payment_type === 'make_payment') {
                $transactionId = $request->payment_intent_id;

                $response = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                    ->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify");
             return   $result = $response->json();

                if ($result['status'] === 'success' && $result['data']['status'] === 'successful') {

                    $amountPaid = $result['data']['amount'];
                    $commission = round(($amountPaid * $profit) / 100, 2);
                    $booking    = Booking::create([
                        'user_id'            => Auth::id(),
                        'provider_id'        => $request->provider_id,
                        'package_id'         => null,
                        'booking_process'    => $request->booking_process,
                        'booking_type'       => $request->booking_type,
                        'schedule_date'      => $request->schedule_date,
                        'schedule_time_slot' => $request->schedule_time_slot,
                        'price'              => $amountPaid,
                        'number_of_people'   => $request->number_of_people,
                        'payment_type'       => $request->payment_type,
                        'payment_intent_id'  => $result['data']['id'],
                        'order_id'           => 'ORD-' . Str::upper(Str::random(10)),
                        'status'             => 'New',
                    ]);
                    Transaction::create([
                        'booking_id'       => $booking->id,
                        'sender_id'        => $booking->user_id,
                        // 'receiver_id'      => $booking->provider_id,
                        'amount'           => $request->price,
                        'transaction_type' => 'purchase',
                        'profit'           => $commission,
                    ]);
                } else {
                    return response()->json(['status' => 'error', 'message' => 'Payment failed'], 400);
                }
            }

            BillingDetail::create([
                'booking_id' => $booking->id,
                'name'       => $request->name,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'location'   => $request->address,
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
            'status'          => 'nullable|in:New,Pending,Completed',
            'booking_process' => 'nullable|in:instant,schedule',
        ]);
        $per_page        = $request->input('per_page', 10);
        $status          = $request->input('status');
        $booking_process = $request->input('booking_process');

        $bookings = Booking::with('user:id,name,avatar,kyc_status')
            ->withCount('booking_items')
            ->where('provider_id', Auth::id())
            ->when($booking_process, function ($query) use ($booking_process) {
                $query->where('booking_process', $booking_process);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);

            })
            ->latest('id')
            ->paginate($per_page);

        return $this->responseSuccess($bookings, 'Booking data retrieved successfully.');
    }

    public function orderDetails($order_id)
    {
        try {
            $order_details = Booking::with([
                'user:id,name,avatar,email,address,phone,kyc_status',
                'provider' => function ($q) {
                    $q->select('id', 'name', 'avatar', 'kyc_status', 'email', 'address', 'phone')
                        ->withAvg('ratings', 'rating')
                        ->withCount('ratings')
                        ->with([
                            'company:id,provider_id,company_name,company_logo',
                            'provider_services' => function ($q2) {
                                $q2->select('id', 'provider_id', 'service_id')
                                    ->with('service');
                            },
                        ]);
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

                $transactionId = $booking->payment_intent_id;
                $response      = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                    ->post("https://api.flutterwave.com/v3/transactions/{$transactionId}/refund");

                $result = $response->json();

                if ($result['status'] !== 'success') {
                    return $this->responseError(null, 'Refund failed.');
                }

                if ($booking->transaction) {
                             $booking->transaction->update([
                    'receiver_id'      => null,
                    'transaction_type' => 'refund',
                    'profit'           => null,
                ]);
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
            $booking = Booking::with('transaction')->findOrFail($booking_id);
            if ($booking->status === 'Completed') {
                return $this->responseSuccess($booking, 'Booking already completed.', 200);
            }
            if (! $booking->transaction) {
                return $this->responseError(null, 'No transaction found for this booking.', 404);
            }
            $provider = $booking->provider;
            if (! $provider || ! $provider->sub_account_id) {
                return $this->responseError(null, 'Provider has not provided any account information.', 400);
            }
            $amount = $booking->transaction->amount - $booking->transaction->profit;
            if ($amount <= 0) {
                return $this->responseError(null, 'Invalid transfer amount.', 400);
            }
            $user_id     = $booking->user_id;
            $provider_id = $booking->provider_id;
            Message::where(function ($q) use ($user_id, $provider_id) {
                $q->where('sender_id', $user_id)
                    ->where('receiver_id', $provider_id);
            })
                ->orWhere(function ($q) use ($user_id, $provider_id) {
                    $q->where('sender_id', $provider_id)
                        ->where('receiver_id', $user_id);
                })
                ->delete();

            $provider->wallet_balance += $amount;
            $provider->save();

            $booking->transaction->update([
                'receiver_id' => $provider_id,
            ]);

            $booking->status = 'Completed';
            $booking->save();

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
        if (Auth::user()->role == 'ADMIN') {
            $request->validate([
                'reason' => 'required|string|max:1000',
            ]);
        }
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

                $transactionId = $booking->payment_intent_id;
                $response      = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                    ->post("https://api.flutterwave.com/v3/transactions/{$transactionId}/refund");

                $result = $response->json();
                if ($result['status'] !== 'success') {
                    return $this->responseError(null, 'Already refunded.');
                }

            } else {
                return $this->responseError(null, 'Invalid payment type.');
            }

            $booking->status = 'Cancelled';
            $booking->save();

            $transaction = Transaction::where('booking_id', $booking->id)->first();

            if ($transaction) {
                $transaction->update([
                    'receiver_id'      => null,
                    'transaction_type' => 'refund',
                    'profit'           => null,
                ]);
            }
            DB::commit();

            if ($request->reason) {
                $booking->user->notify(new OrderCancelNotification($request->reason, $booking->id));
                $booking->provider->notify(new OrderCancelNotification($request->reason, $booking->id));
            }
            return $this->responseSuccess($booking, 'Order cancelled successfully.');

        } catch (Exception $e) {
            DB::rollBack();
            return $this->responseError($e->getMessage());
        }

    }

    public function adminBookingsList(Request $request)
    {
        $request->validate([
            'filter' => 'nullable|in:New,Pending,Completed',
        ]);
        $per_page = $request->input('per_page', 10);
        $search   = $request->input('search');
        $filter   = $request->input('filter');

        $bookings = Booking::with([
            'user:id,name,avatar,kyc_status',
            'provider:id,name,avatar,kyc_status',
        ])
            ->whereIn('status', ['Pending', 'New', 'Completed'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                })->orWhereHas('provider', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                });
            })
            ->when($filter, function ($query) use ($filter) {
                $query->where('status', $filter);
            })
            ->latest('id')
            ->paginate($per_page);

        return $this->responseSuccess($bookings, 'Bookings retrieved successfully.');
    }

}
