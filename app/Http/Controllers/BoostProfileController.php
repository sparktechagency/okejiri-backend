<?php
namespace App\Http\Controllers;

use App\Http\Requests\BoostProfile\StoreBoostProfileRequest;
use App\Mail\BoostingRequestRejectMail;
use App\Models\BoostProfile;
use App\Models\BoostProfileRequest;
use App\Models\User;
use App\Traits\ApiResponse;
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
                'provider_id'  => $boosting_request->provider_id,
                'started_date' => now(),
                'ending_date'  => now()->addDays($boosting_request->number_of_days),
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
            $reason = $request->input('reason');
            Mail::to($provider->email)->send(new BoostingRequestRejectMail($provider->name, $reason));
            return $this->responseSuccess($boosting_request, 'Boosting request rejected successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function getBoostingProfiles(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $search  = $request->input('search');

        $boosting_profiles = BoostProfile::with('provider:id,name,email,avatar,phone,address,kyc_status', 'provider.provider_services.service:id,name')->when($search, function ($q) use ($search) {
            $q->whereHas('provider', function ($query) use ($search) {
                $query->where(function ($q2) use ($search) {
                    $q2->where('name', 'LIKE', '%' . $search . '%')
                        ->orWhere('email', 'LIKE', '%' . $search . '%');
                });
            });
        })->latest('id')->paginate($perPage);
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

}
