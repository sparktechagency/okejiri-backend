<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'api'], function ($router) {

    Route::prefix('auth/')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
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
        Route::post('logout', [AuthController::class, 'logout']);

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

            Route::apiResource('faqs', FaqController::class)->except('index');
            Route::apiResource('services', ServiceController::class)->except('index');
            Route::apiResource('promotions', PromotionController::class)->except('index');
        });

        // Common routes
        Route::middleware('admin.user')->as('common')->group(function () {
            // Notifications
            // Route::get('notifications', [NotificationController::class, 'notifications']);
            // Route::post('mark-notification/{id}', [NotificationController::class, 'singleMark']);
            // Route::post('mark-all-notification', [NotificationController::class, 'allMark']);

            // Messaging
            // Route::post('send-message', [MessageController::class, 'sendMessage']);
            // Route::post('edit-message/{id}', [MessageController::class, 'editMessage']);
            // Route::get('get-message', [MessageController::class, 'getMessage']);
            // Route::post('mark-as-read', [MessageController::class, 'markAsRead']);
            // Route::delete('unsend-for-me/{id}', [MessageController::class, 'unsendForMe']);
            // Route::delete('unsend-for-everyone/{id}', [MessageController::class, 'unsendForEveryone']);
            // Route::get('search-new-user', [MessageController::class, 'searchNewUser']);
            // Route::get('chat-list', [MessageController::class, 'chatList']);
        });

        // Stripe routes
        // Route::prefix('stripe')->group(function () {
        //     Route::prefix('connected')->group(function () {
        //         Route::post('account-create', [ConnectController::class, 'createAccount']);
        //         Route::post('account-link', [ConnectController::class, 'createAccountLink']);
        //         Route::post('payment-intent', [ConnectController::class, 'createPaymentIntent']);
        //         Route::post('payment-link', [ConnectController::class, 'createPaymentLink']);
        //         Route::post('login-link', [ConnectController::class, 'createLoginLink']);
        //         Route::get('balance', [ConnectController::class, 'getBalance']);
        //         Route::post('payout-instant', [ConnectController::class, 'createInstantPayout']);
        //     });

        //     Route::prefix('payment')->group(function () {
        //         Route::post('payment-intent', [PaymentController::class, 'createPaymentIntent']);
        //         Route::post('payment-link', [PaymentController::class, 'createPaymentLink']);
        //     });

        //     Route::prefix('subscription')->group(function () {

        //     });
        // });
    });
    Route::get('pages', [PageController::class, 'getPage']);
    Route::apiResource('faqs', FaqController::class)->only('index');
    Route::apiResource('services', ServiceController::class)->only('index');
    Route::apiResource('promotions', PromotionController::class)->only('index');
    // Route::post('stripe/connected/transfer-create', [ConnectController::class, 'createTransfer']);
    // Route::post('stripe/webhook', [WebhookController::class, 'handleWebhook']);
});
