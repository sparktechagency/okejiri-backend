<?php

use App\Http\Controllers\Api\AddToCartController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DisputeAppealController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\FavouriteController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\ProviderServiceController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ReferralManagementController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceNearbyController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\Stripe\ConnectController;
use App\Http\Controllers\Api\Stripe\PaymentController;
use App\Http\Controllers\Api\Stripe\WebhookController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletManagementController;
use App\Http\Controllers\BoostProfileController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProviderPortfolioController;
use Illuminate\Support\Facades\Route;

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

    Route::middleware(['auth:api', 'verified.user', 'check.block'])->prefix('/')->group(function () {
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
        Route::get('delivery-time-extension_details/{request_id}', [NotificationController::class, 'deliveryTimeExtensionDetails']);

        // Provider routes
        Route::middleware('provider')->as('provider')->group(function () {
            Route::get('home-data', [HomeController::class, 'homeData']);
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

            Route::get('get-provider-orders', [BookingController::class, 'getProviderOrders']);
            Route::post('request-extend-delivery-time', [BookingController::class, 'requestExtendDeliveryTime']);
            Route::post('order-approve/{booking_id}', [BookingController::class, 'orderApprove']);
            Route::post('order-reject/{booking_id}', [BookingController::class, 'orderReject']);
            Route::post('request-for-delivery/{booking_id}', [BookingController::class, 'requestForDelivery']);

            Route::get('my-employee', [EmployeeController::class, 'index']);
            Route::post('add-employee', [EmployeeController::class, 'store']);
            Route::get('employee/{employee_id}', [EmployeeController::class, 'show']);
            Route::put('edit-employee/{employee_id}', [EmployeeController::class, 'update']);
            Route::delete('delete-employee/{employee_id}', [EmployeeController::class, 'delete']);
            Route::post('assign-employee', [EmployeeController::class, 'assignEmployee']);

            Route::post('withdraw', [PayoutController::class, 'withdrawRequest']);
        });

        // User routes
        Route::middleware('user')->as('user')->group(function () {
            Route::post('click', [BoostProfileController::class, 'increaseClick']);
            Route::post('report-provider', [ReportController::class, 'reportProvider']);

            Route::get('get-packages/{service_id}', [HomeController::class, 'getPackages']);
            Route::get('get-package-detail/{package_id}', [HomeController::class, 'getPackageDetail']);

            Route::get('get-provider-portfolio/{provider_id}', [HomeController::class, 'getProviderPortfolio']);
            Route::get('get-provider-profile/{provider_id}', [HomeController::class, 'getProviderProfile']);
            Route::get('get-provider-review/{provider_id}', [HomeController::class, 'getProviderReview']);
            Route::get('get-provider-services/{provider_id}', [HomeController::class, 'getProviderServices']);

            Route::post('deposit-success', [WalletManagementController::class, 'depositSuccess']);
            Route::get('services-nearby', [ServiceNearbyController::class, 'servicesNearby']);

            Route::get('/favorites', [FavouriteController::class, 'index']);
            Route::post('/favorites', [FavouriteController::class, 'store']);
            Route::delete('/favorites/{package_id}', [FavouriteController::class, 'destroy']);

            Route::get('/get-cart-items', [AddToCartController::class, 'index']);
            Route::post('/store-delete-cart-item', [AddToCartController::class, 'storeOrDelete']);
            Route::delete('/delete-cart-items', [AddToCartController::class, 'deleteAllCartItem']);

            Route::get('/get-provider-discount', [BookingController::class, 'getProviderDiscount']);
            Route::post('/booking', [BookingController::class, 'create']);

            Route::post('/rating', [RatingController::class, 'create']);
            Route::post('/delivery-time-extension/accept/{request_id}', [BookingController::class, 'acceptExtendDeliveryTime']);
            Route::post('/delivery-time-extension/decline/{request_id}', [BookingController::class, 'declineExtendDeliveryTime']);
            Route::post('accept-delivery-request/{booking_id}', [BookingController::class, 'acceptDeliveryRequest']);
            Route::post('decline-delivery-request/{booking_id}', [BookingController::class, 'declineDeliveryRequest']);
            Route::get('my-bookings', [BookingController::class, 'myBookings']);
            Route::get('bookings-history', [BookingController::class, 'bookingsHistory']);

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
            Route::post('delete-boosting-profiles/{id}', [BoostProfileController::class, 'deleteBoostingProfiles']);

            Route::get('get-reports', [ReportController::class, 'getReports']);
            Route::get('get-report-detail/{report_id}', [ReportController::class, 'getReportDetail']);
            Route::delete('delete-reports/{report_id}', [ReportController::class, 'deleteReports']);
            Route::post('take-report-action/{report_id}', [ReportController::class, 'takeReportAction']);

            Route::get('transactions', [WalletManagementController::class, 'transactions']);
            Route::get('user-transactions/{user_id}', [WalletManagementController::class, 'userTransactions']);
            Route::get('provider-transactions/{provider_id}', [WalletManagementController::class, 'providerTransactions']);

            Route::get('referral-management', [ReferralManagementController::class, 'referralManagement']);
            Route::get('referral-management/{refer_id}', [ReferralManagementController::class, 'referralManagementDetail']);

            Route::get('bookings', [BookingController::class, 'adminBookingsList']);
            Route::get('dashboard', DashboardController::class);

            Route::get('get-disputes', [DisputeController::class, 'getAdminDispute']);
            Route::get('get-disputes-details/{dispute_id}', [DisputeController::class, 'getAdminDisputeDetails']);
            Route::post('dispute-action/{dispute_id}', [DisputeController::class, 'disputeAction']);
            Route::post('dispute-mail', [DisputeController::class, 'disputeMail']);

            Route::get('payout-requests', [PayoutController::class, 'payoutRequests']);
            Route::get('payout-request/{id}', [PayoutController::class, 'payoutRequestsDetails']);
            Route::get('previous-payouts/{provider_id}', [PayoutController::class, 'previousPayouts']);
            Route::post('payout-rejected/{id}', [PayoutController::class, 'payoutRejected']);
            Route::post('payout-accepted/{id}', [PayoutController::class, 'payoutAccepted']);
            Route::post('bulk-payout-accept-reject', [PayoutController::class, 'bulkPayoutAcceptReject']);
        });

        // user.provider routes
        Route::middleware('user.provider')->as('user.provider')->group(function () {
            Route::post('switch-role', [AuthController::class, 'switchRole']);
            Route::get('my-referrals', [ReferralManagementController::class, 'myReferrals']);

            Route::get('boost-my-profile', [BoostProfileController::class, 'getMyBoostMyProfile']);
            Route::post('boost-my-profile', [BoostProfileController::class, 'boostMyProfile']);

            Route::get('my-transactions', [WalletManagementController::class, 'myTransactions']);
            Route::post('transfer-balance', [WalletManagementController::class, 'transferBalance']);

            Route::post('add-dispute', [DisputeController::class, 'addDispute']);
            Route::get('my-dispute', [DisputeController::class, 'myDispute']);
            Route::get('dispute-details/{dispute_id}', [DisputeController::class, 'DisputeDetails']);
            Route::delete('dispute-delete/{dispute_id}', [DisputeController::class, 'DisputeDelete']);

            Route::post('add-dispute-appeal', [DisputeAppealController::class, 'addDisputeAppeal']);
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

            Route::get('order-details/{order_id}', [BookingController::class, 'orderDetails']);
            Route::post('order-cancel/{order_id}', [BookingController::class, 'orderCancel']);
        });

        // Stripe routes
        Route::prefix('stripe')->group(function () {
            Route::prefix('connected')->group(function () {
                Route::post('account-create', [ConnectController::class, 'createAccount']);
                Route::get('balance', [ConnectController::class, 'getBalance']);
            });

            Route::prefix('payment')->group(function () {
                Route::post('payment-intent', [PaymentController::class, 'createPaymentIntent']);
            });
        });
    });
    Route::get('get-settings', [SettingController::class, 'getSettings']);
    Route::get('pages', [PageController::class, 'getPage']);
    Route::apiResource('faqs', FaqController::class)->only('index');
    Route::apiResource('services', ServiceController::class)->only('index');
    Route::apiResource('promotions', PromotionController::class)->only('index');
    Route::post('stripe/webhook', [WebhookController::class, 'handleWebhook']);
});
