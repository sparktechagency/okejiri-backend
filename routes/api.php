<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\BoostProfileController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\ProviderPortfolioController;
use App\Http\Controllers\Api\Stripe\PaymentController;
use App\Http\Controllers\Api\ProviderServiceController;
use App\Http\Controllers\Api\ReferralManagementController;

Route::group(['middleware' => 'api'], function ($router) {

    Route::prefix('auth/')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::put('complete-personalizations/{user_id}', [AuthController::class, 'completePersonalization']);
        Route::post('social-login', [AuthController::class, 'socialLogin']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('otp-verification', [AuthController::class, 'otpVerify']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::post('check-token', [AuthController::class, 'validateToken']);
    });

    Route::middleware(['auth:api', 'verified.user'])->prefix('/')->group(function () {
        Route::get('profile', [AuthController::class, 'getProfile']);
        Route::post('edit-profile', [AuthController::class, 'editProfile']);
        Route::post('edit-profile-picture', [AuthController::class, 'editProfilePicture']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('delete-profile', [AuthController::class, 'deleteProfile']);
        Route::post('complete-kyc', [AuthController::class, 'completeKyc']);
        Route::post('request-add-service', [ServiceController::class, 'requestAddService']);
        Route::post('logout', [AuthController::class, 'logout']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'notifications']);
        Route::post('mark-notification/{id}', [NotificationController::class, 'singleMark']);
        Route::post('mark-all-notification', [NotificationController::class, 'allMark']);

        // Provider routes
        Route::middleware('provider')->as('provider')->group(function () {
            Route::apiResource('portfolios', ProviderPortfolioController::class);
            Route::post('manage-discounts', [ProviderController::class, 'manageDiscounts']);
            Route::get('my-services', [ProviderController::class, 'myServices']);
            Route::delete('delete-my-services/{provider_service_id}', [ProviderController::class, 'deleteMyServices']);
            Route::post('add-new-services', [ProviderController::class, 'addNewServices']);

            Route::get('my-service-package', [ProviderServiceController::class, 'myServicePackage']);
            Route::post('my-service-package', [ProviderServiceController::class, 'addMyServicePackage']);
            Route::get('my-service-package/{package_id}', [ProviderServiceController::class, 'myServicePackageDetails']);
            Route::put('my-service-package/{package_id}', [ProviderServiceController::class, 'myServicePackageEdit']);
            Route::post('add-service-package-detail-item', [ProviderServiceController::class, 'addServicePackageItem']);
            Route::delete('delete-service-package-detail-item/{package_id}', [ProviderServiceController::class, 'deleteServicePackageItem']);
            Route::post('add-service-available-time', [ProviderServiceController::class, 'addServiceAvailableTime']);
            Route::put('update-service-available-time/{package_id}', [ProviderServiceController::class, 'updateServiceAvailableTime']);
        });

        // User routes
        Route::middleware('user')->as('user')->group(function () {

        });

        // Admin routes
        Route::middleware('admin')->prefix('admin/')->as('admin')->group(function () {
            // Route::post('block-toggle/{user_id}', [AuthController::class, 'toggleBlockStatus']);
            Route::post('pages', [PageController::class, 'createOrUpdatePage']);
            Route::get('requested-services', [ServiceController::class, 'requestedServices']);
            Route::delete('requested-services/{id}', [ServiceController::class, 'deleteRequestedServices']);
            Route::post('requested-services', [ServiceController::class, 'addRequestedServices']);

            // users
            Route::get('users', [UserController::class, 'index']);
            Route::post('sent-notifications', [UserController::class, 'sentNotifications']);
            Route::get('kyc-requests', [UserController::class, 'getKycRequests']);
            Route::get('kyc-requests-details/{user_id}', [UserController::class, 'getKycRequestDetails']);
            Route::post('accept-kyc/{id}', [UserController::class, 'acceptKyc']);
            Route::post('reject-kyc/{id}', [UserController::class, 'rejectKyc']);
            Route::delete('delete-users/{user_id}', [UserController::class, 'deleteUsers']);
            Route::get('user-details/{user_id}', [UserController::class, 'userDetails']);

            Route::get('get-settings', [SettingController::class, 'getSettings']);
            Route::post('update-settings', [SettingController::class, 'updateSettings']);
            Route::apiResource('faqs', FaqController::class)->except('index');
            Route::apiResource('services', ServiceController::class)->except('index');
            Route::apiResource('promotions', PromotionController::class)->except('index');

            Route::get('get-boosting-requests', [BoostProfileController::class, 'getBoostingRequests']);
            Route::get('get-boosting-requests/{request_id}', [BoostProfileController::class, 'getBoostingRequestDetails']);
            Route::post('accept-boosting-requests/{request_id}', [BoostProfileController::class, 'acceptBoostingRequest']);
            Route::post('reject-boosting-requests/{request_id}', [BoostProfileController::class, 'rejectBoostingRequest']);
            Route::get('get-boosting-profiles', [BoostProfileController::class, 'getBoostingProfiles']);
            Route::get('get-boosting-profiles/{id}', [BoostProfileController::class, 'getBoostingProfileDetails']);
            Route::post('toggle-boosting-status/{id}', [BoostProfileController::class, 'toggleBoostingStatus']);
        });

        // user.provider routes
        Route::middleware('user.provider')->as('user.provider')->group(function () {
            Route::post('switch-role', [AuthController::class, 'switchRole']);
            Route::get('my-referrals', [ReferralManagementController::class, 'myReferrals']);

            Route::post('boost-my-profile', [BoostProfileController::class, 'boostMyProfile']);
        });

        Route::middleware('admin.user.provider')->as('common')->group(function () {
            // Messaging
            Route::post('send-message', [MessageController::class, 'sendMessage']);
            Route::post('edit-message/{id}', [MessageController::class, 'editMessage']);
            Route::get('get-message', [MessageController::class, 'getMessage']);
            Route::post('mark-as-read', [MessageController::class, 'markAsRead']);
            Route::delete('unsend-for-me/{id}', [MessageController::class, 'unsendForMe']);
            Route::delete('unsend-for-everyone/{id}', [MessageController::class, 'unsendForEveryone']);
            Route::get('search-new-user', [MessageController::class, 'searchNewUser']);
            Route::get('chat-list', [MessageController::class, 'chatList']);
        });

        // Stripe routes
        Route::prefix('stripe')->group(function () {
        //     Route::prefix('connected')->group(function () {
        //         Route::post('account-create', [ConnectController::class, 'createAccount']);
        //         Route::post('account-link', [ConnectController::class, 'createAccountLink']);
        //         Route::post('payment-intent', [ConnectController::class, 'createPaymentIntent']);
        //         Route::post('payment-link', [ConnectController::class, 'createPaymentLink']);
        //         Route::post('login-link', [ConnectController::class, 'createLoginLink']);
        //         Route::get('balance', [ConnectController::class, 'getBalance']);
        //         Route::post('payout-instant', [ConnectController::class, 'createInstantPayout']);
        //     });

            Route::prefix('payment')->group(function () {
                Route::post('payment-intent', [PaymentController::class, 'createPaymentIntent']);
            });

        //     Route::prefix('subscription')->group(function () {

        //     });
        });
    });
    Route::get('pages', [PageController::class, 'getPage']);
    Route::apiResource('faqs', FaqController::class)->only('index');
    Route::apiResource('services', ServiceController::class)->only('index');
    Route::apiResource('promotions', PromotionController::class)->only('index');
    // Route::post('stripe/connected/transfer-create', [ConnectController::class, 'createTransfer']);
    // Route::post('stripe/webhook', [WebhookController::class, 'handleWebhook']);
});
