<?php
namespace App\Http\Controllers;

use App\Http\Requests\BoostProfile\StoreBoostProfileRequest;
use App\Mail\BoostingRequestRejectMail;
use App\Models\BoostProfile;
use App\Models\BoostProfileRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class BoostProfileController extends Controller
{
    use ApiResponse;

    public function boostMyProfile(StoreBoostProfileRequest $request)
    {
        $user = Auth::user();
        if ($request->payment_method === 'referral_balance') {
            if ($user->referral_balance < $request->payment_amount) {
                return $this->responseError(null, 'Insufficient referral balance.');

            }
            $user->referral_balance -= $request->payment_amount;
            $user->save();
        }

        $boost_profile = BoostProfileRequest::create([
            'provider_id'       => $user->id,
            'number_of_days'    => $request->number_of_days,
            'payment_method'    => $request->payment_method,
            'payment_amount'    => $request->payment_amount,
            'payment_intent_id' => $request->payment_intent_id,
        ]);
        return $this->responseSuccess($boost_profile,
            'Boost request submitted successfully.',
        );
    }

    public function getMyBoostMyProfile()
    {
        $get_boosted_data = BoostProfile::with('boostingRequest')->where('provider_id', Auth::id())
            ->latest('id')
            ->first();

        $today = now()->startOfDay();
        $data  = [];

        if ($get_boosted_data) {
            $endingDate = Carbon::parse($get_boosted_data->ending_date)->startOfDay();

            if ($endingDate->lt($today)) {
                $boosting_status = 'Expired';
            } elseif ($get_boosted_data->is_boosting_pause == false) {
                $boosting_status = 'Active';
            } else {
                $boosting_status = 'Pause';
            }

            $days_remaining = $endingDate->lt($today)
                ? 0
                : $today->diffInDays($endingDate);
            $data = [
                'id'               => $get_boosted_data->id,
                'provider_id'      => $get_boosted_data->provider_id,
                'boost_request_id' => $get_boosted_data->boost_request_id,
                'started_date'     => $get_boosted_data->started_date,
                'ending_date'      => $get_boosted_data->ending_date,
                'total_click'      => $get_boosted_data->total_click,
                'total_bookings'   => $get_boosted_data->total_bookings,
                'boosting_status'  => $boosting_status,
                'payment_amount'   => optional($get_boosted_data->boostingRequest)->payment_amount,
                'number_of_days'   => optional($get_boosted_data->boostingRequest)->number_of_days,
                'days_remaining'   => $days_remaining,
                'created_at'       => $get_boosted_data->created_at,
                'updated_at'       => $get_boosted_data->updated_at,
            ];
        } else {
            $data = [
                'boosting_status' => 'No Boosting Found',
            ];
        }

        return $this->responseSuccess($data, 'Current Boosting details retrieved successfully');
    }

    public function getBoostingRequests(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $search  = $request->input('search');

        $requested_boostings = BoostProfileRequest::with('provider:id,name,email,avatar,phone,address,kyc_status', 'provider.provider_services.service:id,name')->when($search, function ($q) use ($search) {
            $q->whereHas('provider', function ($query) use ($search) {
                $query->where(function ($q2) use ($search) {
                    $q2->where('name', 'LIKE', '%' . $search . '%')
                        ->orWhere('email', 'LIKE', '%' . $search . '%');
                });
            });
        })->where('status', 'pending')->latest('id')->paginate($perPage);
        return $this->responseSuccess($requested_boostings, 'Boosting request retrieved successfully');
    }
    public function getBoostingRequestDetails($request_id)
    {
        $requested_boosting_details = BoostProfileRequest::with('provider:id,name,email,avatar,phone,address,kyc_status', 'provider.provider_services.service:id,name')->findOrFail($request_id);
        return $this->responseSuccess($requested_boosting_details, 'Boosting request details retrieved successfully');
    }

    public function acceptBoostingRequest($request_id)
    {
        try {
            $boosting_request = BoostProfileRequest::findOrFail($request_id);
            if (in_array($boosting_request->status, ['reject', 'accept'])) {
                return $this->responseError(null, 'This Boosting request is already accepted or rejected.');
            }
            $boosting_request->status = 'accept';
            $boosting_request->save();

            $provider             = User::find($boosting_request->provider_id);
            $provider->is_boosted = true;
            $provider->save();

            $boost_profile = BoostProfile::create([
                'provider_id'      => $boosting_request->provider_id,
                'boost_request_id' => $boosting_request->id,
                'started_date'     => now(),
                'ending_date'      => now()->addDays($boosting_request->number_of_days),
            ]);

            return $this->responseSuccess($boosting_request, 'Boosting request accepted successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
    public function rejectBoostingRequest(Request $request, $request_id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);
        try {
            $boosting_request = BoostProfileRequest::findOrFail($request_id);
            if (in_array($boosting_request->status, ['reject', 'accept'])) {
                return $this->responseError(null, 'This Boosting request is already accepted or rejected.');
            }

            if ($boosting_request->payment_method == 'referral_balance') {
                $provider = User::find($boosting_request->provider_id);
                $provider->referral_balance += $boosting_request->payment_amount;
                $provider->save();
            } elseif ($boosting_request->payment_method == 'stripe') {
                Stripe::setApiKey(config('services.stripe.secret'));

                $intent   = PaymentIntent::retrieve($boosting_request->payment_intent_id);
                $chargeId = $intent->latest_charge;

                if (! $chargeId) {
                    return $this->responseError(null, 'Could not find any charge information for this payment.');
                }
                $refund = Refund::create([
                    'charge' => $chargeId,
                ]);
            }
            $boosting_request->status = 'reject';
            $boosting_request->save();
            $reason  = $request->input('reason');
            $subject = 'Boosting Request Rejected';
            Mail::to($provider->email)->send(new BoostingRequestRejectMail($provider->name, $reason, 'rejected', $subject,'boosting'));
            return $this->responseSuccess($boosting_request, 'Boosting request rejected successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function getBoostingProfiles(Request $request)
    {
        $today   = Carbon::now()->startOfDay();
        $perPage = $request->input('per_page', 20);
        $search  = $request->input('search');

        $boosting_profiles = BoostProfile::with('provider:id,name,email,avatar,phone,address,kyc_status', 'provider.provider_services.service:id,name')->when($search, function ($q) use ($search) {
            $q->whereHas('provider', function ($query) use ($search) {
                $query->where(function ($q2) use ($search) {
                    $q2->where('name', 'LIKE', '%' . $search . '%')
                        ->orWhere('email', 'LIKE', '%' . $search . '%');
                });
            });
        })->latest('id')->whereDate('ending_date', '>=', $today)->paginate($perPage);
        $metadata['request'] = BoostProfileRequest::where('status', 'pending')->count();
        return $this->responseSuccess($boosting_profiles, 'Boosting profiles retrieved successfully', 200, 'success', $metadata);
    }
    public function getBoostingProfileDetails($id)
    {
        $boosting_profile_details = BoostProfile::with('provider:id,name,email,avatar,phone,address,kyc_status', 'provider.provider_services.service:id,name')->findOrFail($id);
        return $this->responseSuccess($boosting_profile_details, 'Boosting profiles details retrieved successfully');
    }
    public function toggleBoostingStatus($id)
    {
        $boosting_profile                    = BoostProfile::findOrFail($id);
        $boosting_profile->is_boosting_pause = ! $boosting_profile->is_boosting_pause;
        $boosting_profile->save();
        $status = $boosting_profile->is_boosting_pause ? 'paused' : 'resumed';

        $provider = User::find($boosting_profile->provider_id);
        if ($boosting_profile->is_boosting_pause == true) {
            $provider->is_boosted = false;
        } else {
            $provider->is_boosted = true;
        }
        $provider->save();
        return $this->responseSuccess(
            $boosting_profile,
            "Boosting profile has been {$status} successfully."
        );
    }
    public function deleteBoostingProfiles(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        try {
            $boosting_profile     = BoostProfile::findOrFail($id);
            $provider             = User::find($boosting_profile->provider_id);
            $provider->is_boosted = false;
            $provider->save();
            $boosting_profile->delete();
            $reason  = $request->input('reason');
            $subject = ' Boosting Removed';
            Mail::to($provider->email)->send(new BoostingRequestRejectMail($provider->name, $reason, 'removed', $subject,'boosting'));
            return $this->responseSuccess($boosting_profile, 'Boosting request deleted successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function increaseClick(Request $request)
    {
        $request->validate([
            'provider_id' => 'required|numeric',
        ]);

        $today = now()->startOfDay();

        $boost = BoostProfile::where('provider_id', $request->provider_id)
            ->latest('id')
            ->first();

        if (! $boost) {
            return $this->responseError(null, 'Boosting not found', 400);
        }
        if (Carbon::parse($boost->ending_date)->lt($today)) {
            return $this->responseError(null, 'Boosting has expired', 400);
        }

        if ($boost->is_boosting_pause == 1) {
            return $this->responseError(null, 'Boosting is paused', 400);
        }
        $boost->total_click += 1;
        $boost->save();

        return $this->responseSuccess($boost, 'Click counted successfully');

    }

}
