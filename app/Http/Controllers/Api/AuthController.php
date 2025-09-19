<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\CompleteKYCRequest;
use App\Http\Requests\Auth\CompletePersonalizationRequest;
use App\Http\Requests\Auth\DeleteProfileRequest;
use App\Http\Requests\Auth\EditProfilePictureRequest;
use App\Http\Requests\Auth\EditProfileRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OTPVerificationRequest;
use App\Http\Requests\Auth\RegistrationRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Mail\OtpMail;
use App\Models\Company;
use App\Models\ProviderService;
use App\Models\User;
use App\Notifications\CompleteKYCNotification;
use App\Services\FileUploadService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use ApiResponse;
    protected $fileuploadService;
    private $avatarPath  = 'uploads/users/avatar';
    private $defaultFile = ['default_avatar.png'];

    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setPath($this->avatarPath)->setDefaultFiles($this->defaultFile);
    }

    public function register(RegistrationRequest $request)
    {
        DB::beginTransaction();
        try {
            $user_exists = User::where('email', $request->email)->first();

            if ($user_exists && $user_exists->email_verified_at !== null) {
                $meta_data = ['redirect_login' => true];
                return $this->responseError(
                    null,
                    'An account with this email has already been verified. Please log in to continue.',
                    409,
                    'error',
                    $meta_data
                );
            }

            $otp            = rand(000000, 999999);
            $otp_expires_at = now()->addMinutes(10);

            if ($user_exists && $user_exists->email_verified_at === null) {
                $user_exists->name           = $request->name;
                $user_exists->role           = $request->role ?? 'USER';
                $user_exists->provider_type  = $request->provider_type ?? null;
                $user_exists->password       = Hash::make($request->password);
                $user_exists->otp            = $otp;
                $user_exists->otp_expires_at = $otp_expires_at;
                $user_exists->status         = 'inactive';

                if ($user_exists->avatar) {
                    $this->fileuploadService->deleteFile($user_exists->avatar);
                }

                $user_exists->avatar = $request->hasFile('photo')
                    ? $this->fileuploadService->saveOptimizedImage($request->file('photo'), 40, 512, null, true)
                    : $this->fileuploadService->generateUserAvatar($request->name);
                if ($request->referral_code) {
                    $referred_by              = User::where('role', $request->role)->where('referral_code', $request->referral_code)->first()?->id;
                    $user_exists->referred_by = $referred_by;
                }
                $user_exists->save();
                $user = $user_exists;

            } else {
                $new_user                 = new User();
                $new_user->name           = $request->name;
                $new_user->email          = $request->email;
                $new_user->password       = Hash::make($request->password);
                $new_user->role           = $request->role ?? 'USER';
                $new_user->provider_type  = $request->provider_type ?? null;
                $new_user->otp            = $otp;
                $new_user->otp_expires_at = $otp_expires_at;
                $new_user->status         = 'inactive';
                $new_user->referral_code  = rand(100000, 999999);

                $new_user->avatar = $request->hasFile('photo')
                    ? $this->fileuploadService->saveOptimizedImage($request->file('photo'), 40, 512, null, true)
                    : $this->fileuploadService->generateUserAvatar($request->name);

                if ($request->referral_code) {
                    $referred_by           = User::where('role', $request->role)->where('referral_code', $request->referral_code)->first()?->id;
                    $new_user->referred_by = $referred_by;
                }
                $new_user->save();
                $user = $new_user;

                $user->notify(new CompleteKYCNotification());
            }

            $this->sendMail($user->email, $otp, 'register');
            DB::commit();

            $meta_data = ['redirect_verification' => true];
            return $this->responseSuccess(
                $user,
                $user_exists
                    ? 'You have already registered but not verified your email. Please verify to continue'
                    : 'An OTP has been sent to your email. Please verify to complete registration.',
                $user_exists ? 200 : 201,
                'success',
                $meta_data
            );

        } catch (Exception $e) {
            DB::rollBack();
            return $this->responseError($e->getMessage(), 'An error occurred while registering the user.');
        }
    }

    public function completePersonalization(CompletePersonalizationRequest $request, $user_id)
    {
        try {
            $user            = User::findOrFail($user_id);
            $user->phone     = $request->phone;
            $user->address   = $request->address;
            $user->latitude  = $request->latitude;
            $user->longitude = $request->longitude;
            $user->about     = $request->about;
            if ($user->role === 'PROVIDER' && $request->has('service_id')) {
                foreach ($request->service_id as $serviceId) {
                    ProviderService::create([
                        'provider_id' => $user->id,
                        'service_id'  => $serviceId,
                    ]);
                }
            }
            if ($request->provider_type == 'Company') {
                $company               = new Company();
                $company->provider_id  = $user_id;
                $company->company_logo = $request->hasFile('business_logo')
                    ? $this->fileuploadService->setPath('uploads/companies')->saveOptimizedImage($request->file('business_logo'), 40, 512, null, true)
                    : $this->fileuploadService->setPath('uploads/companies')->generateUserAvatar($request->business_name);
                $company->company_name     = $request->business_name;
                $company->company_location = $request->business_location;
                $company->company_about    = $request->about_business;
                $company->emp_no           = $request->emp_no;
                $company->save();
            }
            $user->is_personalization_complete = true;
            $user->save();
            return $this->responseSuccess($user, 'Personalization completed successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'An error occurred while complete the personalization.');
        }
    }

    public function socialLogin(SocialLoginRequest $request)
    {
        try {
            $user_exists = User::where('email', $request->email)->first();
            if ($user_exists) {
                if ($user_exists->role !== $request->role) {
                    return $this->responseError(null, 'You are not authorized as this role.', 403);
                }

                if ($request->role === 'PROVIDER') {
                    if ($user_exists->provider_type !== $request->provider_type) {
                        return $this->responseError(null, 'Provider type mismatch.', 403);
                    }
                }
                $socialId = ($request->has('google_id') && $user_exists->google_id === $request->google_id) || ($request->has('facebook_id') && $user_exists->facebook_id === $request->facebook_id) || ($request->has('twitter_id') && $user_exists->twitter_id === $request->twitter_id) || ($request->has('apple_id') && $user_exists->apple_id === $request->apple_id);
                if ($socialId) {
                    $responseWithToken = $this->generateTokenResponse($user_exists);
                    return $this->responseSuccess($responseWithToken, 'You have successfully logged in.');
                } elseif (is_null($user_exists->google_id) && is_null($user_exists->facebook_id) && is_null($user_exists->twitter_id) && is_null($user_exists->apple_id)) {
                    $meta_data = ['redirect_login' => true];
                    return $this->responseSuccess(null, 'An account with this email already exists. Please sign in instead.', 200, 'success', $meta_data);
                } else {
                    $user_exists->update([
                        'google_id' => $request->google_id ?? $user_exists->google_id,
                    ]);
                    $responseWithToken = $this->generateTokenResponse($user_exists);
                    return $this->responseSuccess($responseWithToken, 'You have successfully logged in.');
                }
            }
            $new_user                              = new User();
            $new_user->name                        = $request->name;
            $new_user->email                       = $request->email;
            $new_user->role                        = $request->role ?? 'USER';
            $new_user->provider_type               = $request->provider_type ?? null;
            $new_user->password                    = Hash::make(Str::random(16));
            $new_user->google_id                   = $request->google_id ?? null;
            $new_user->email_verified_at           = now();
            $new_user->status                      = 'active';
            $new_user->is_personalization_complete = false;

            $new_user->avatar = $request->hasFile('photo')
                ? $this->fileuploadService->saveOptimizedImage($request->file('photo'), 40, 512, null, true)
                : $this->fileuploadService->generateUserAvatar($request->name);

            $new_user->save();
            $new_user->notify(new CompleteKYCNotification());

            $responseWithToken = $this->generateTokenResponse($new_user);
            return $this->responseSuccess($responseWithToken, 'You have successfully logged in.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'An error occurred while registering the user.');
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();
            if ($user->role !== $request->role) {
                return $this->responseError(null, 'You are not authorized as this role.', 403);
            }

            if ($request->role === 'PROVIDER') {
                if ($user->provider_type !== $request->provider_type) {
                    return $this->responseError(null, 'Provider type mismatch.', 403);
                }
            }
            if ($user->email_verified_at == null) {
                $otp                  = rand(000000, 999999);
                $otp_expires_at       = now()->addMinutes(10);
                $user->otp            = $otp;
                $user->otp_expires_at = $otp_expires_at;

                // Send OTP
                $this->sendMail($request->email, $otp, 'register');

                $meta_data = ['redirect_verification' => true];
                return $this->responseError(null, 'Your account is not verified. OTP has been sent to your email.', 403, 'error', $meta_data);
            }

            $credentials = $request->only(['email', 'password']);
            if (! $token = auth()->attempt($credentials)) {
                return $this->responseError(null, 'Invalid email or password', 401);
            }
            $user              = auth()->user();
            $responseWithToken = $this->generateTokenResponse($user);
            $user->status      = 'active';
            $user->save();
            return $this->responseSuccess($responseWithToken, 'You have successfully logged in.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function otpVerify(OTPVerificationRequest $request)
    {
        $user = User::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('otp_expires_at', '>=', now())
            ->first();
        if (! $user) {
            return $this->responseError(null, 'OTP is incorrect or has expired.', 400);
        }

        $user->email_verified_at = now();
        $user->otp               = null;
        $user->otp_expires_at    = null;
        $user->status            = 'active';
        $user->save();
        Auth::login($user);
        $responseWithToken = $this->generateTokenResponse($user);
        return $this->responseSuccess($responseWithToken, 'You have successfully logged in.');
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            $user                 = User::where('email', $request->email)->first();
            $otp                  = rand(000000, 999999);
            $otp_expires_at       = now()->addMinutes(10);
            $user->otp            = $otp;
            $user->otp_expires_at = $otp_expires_at;
            $user->save();

            // Send OTP
            $this->sendMail($request->email, $otp, 'reset_password');
            // $this->sendSms($user->phone, $otp);

            $meta_data = ['redirect_verification' => true];
            return $this->responseSuccess(null, 'A OTP has been sent to your email.', 200, 'success', $meta_data);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->password = Hash::make($request->password);
            $user->save();
            return $this->responseSuccess(null, 'Password has been reset successfully.');
        }
        return $this->responseError(null, 'No user found with this email address.', 404);
    }

    public function getProfile()
    {
        $user = User::with('company', 'provider_services.service')->where('id', Auth::id())->first();

        return $this->responseSuccess($user, ucfirst(strtolower($user->role)) . " profile retrieved successfully.");
    }

    public function editProfile(EditProfileRequest $request)
    {
        $user          = Auth::user();
        $user->name    = $request->name ?? $user->name;
        $user->phone   = $request->phone ?? $user->phone;
        $user->address = $request->address ?? $user->address;
        $user->save();
        return $this->responseSuccess($user, 'User profile updated successfully.');
    }

    public function editProfilePicture(EditProfilePictureRequest $request)
    {
        $user         = Auth::user();
        $user->avatar = $this->fileuploadService->updateOptimizedImage($request->file('photo'), $user->avatar, 40, 512, null, true);
        $user->save();
        return $this->responseSuccess($user, 'Profile picture updated successfully.');
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = Auth::user();
        if (! Hash::check($request->current_password, $user->password)) {
            return $this->responseError(null, 'Current password is incorrect.', 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return $this->responseSuccess(null, 'Password changed successfully.');
    }

    public function deleteProfile(DeleteProfileRequest $request)
    {
        $user = Auth::user();

        if (! Hash::check($request->password, $user->password)) {
            return $this->responseError(null, 'Password is incorrect.', 400);
        }

        if ($user->role === 'ADMIN' && $user->id === 1) {
            return $this->responseError(null, 'Admin accounts cannot be deleted.', 403);
        }

        if ($user->role === 'USER' && $user->id === 2) {
            return $this->responseError(null, 'This is a testing user and cannot be deleted.', 403);
        }

        $this->fileuploadService->deleteFile($user->avatar);
        $user->delete();

        return $this->responseSuccess(null, 'Your account has been deleted successfully.');
    }

    public function logout()
    {
        $user         = Auth::user();
        $user->status = 'inactive';
        $user->save();
        auth()->logout();
        $meta_data = ['redirect_login' => true];
        return $this->responseSuccess(null, 'Successfully logged out.', 200, 'success', $meta_data);
    }

    public function validateToken(Request $request)
    {
        try {
            $token = $request->bearerToken();

            if (! $token) {
                $meta_data = ['token_status' => false];
                return $this->responseError(null, 'Authentication token is missing. Please provide a valid token.', 401, 'error', $meta_data);
            }

            $user = JWTAuth::setToken($token)->authenticate();

            if ($user) {
                $meta_data = ['token_status' => true];
                return $this->responseSuccess(null, 'Token is valid and user is authenticated.', 200, 'success', $meta_data);
            }
            $meta_data = ['token_status' => false];
            return $this->responseError(null, 'Token is valid but user is not authenticated.', 401, 'error', $meta_data);

        } catch (JWTException $e) {
            $meta_data = ['token_status' => false];
            return $this->responseError(null, 'Token is invalid or has expired. Please log in again.', 401, 'error', $meta_data);
        }
    }

    public function toggleBlockStatus($user_id)
    {
        $user = User::find($user_id);
        if (! $user) {
            return $this->responseError(null, 'User not found.', 404);
        }

        $user->is_blocked = ! $user->is_blocked;
        $user->save();

        $status = $user->is_blocked ? 'blocked' : 'unblocked';
        return $this->responseSuccess($user, ucfirst(strtolower($user->role)) . " has been successfully {$status}.");
    }

    private function sendMail($email, $otp, $type)
    {
        try {
            Mail::to($email)->send(new OtpMail($otp, $type));
        } catch (Exception $e) {
            throw new Exception("Email sending failed: " . $e->getMessage());
        }
    }

    public function completeKyc(CompleteKYCRequest $request)
    {
        $user = Auth::user();
        if ($request->hasFile('id_card_front')) {
            $user->id_card_front = $this->fileuploadService->setPath('uploads/users/kyc/id_card_front')->updateOptimizedImage($request->file('id_card_front'), $user->id_card_front, 40, 1320, null, true);
        }
        if ($request->hasFile('id_card_back')) {
            $user->id_card_back = $this->fileuploadService->setPath('uploads/users/kyc/id_card_back')->updateOptimizedImage($request->file('id_card_back'), $user->id_card_back, 40, 1320, null, true);
        }
        if ($request->hasFile('selfie')) {
            $user->selfie = $this->fileuploadService->setPath('uploads/users/kyc/selfie')->updateOptimizedImage($request->file('selfie'), $user->selfie, 40, 1320, null, true);
        }
        $user->kyc_status = 'In Review';
        $user->save();
        return $this->responseSuccess(null, 'Kyc verification apply successfully.');
    }

    private function generateTokenResponse($user)
    {
        $token = JWTAuth::fromUser($user);

        return [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth()->factory()->getTTL() * 60,
            'user'         => $user,
        ];
    }
}
