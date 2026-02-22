<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\MidtransCallbackController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShippingAddressController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use App\Models\AiInsightLog;
use App\Http\Controllers\AiInsightController;
use App\Http\Controllers\AiRecommendationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\CancelController;
use App\Http\Controllers\WithdrawController;
use App\Http\Controllers\TenunGuideController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('ai')->group(function () {
    Route::get('/buyer',  [AiInsightController::class, 'buyerInsight']);
    Route::get('/seller', [AiInsightController::class, 'sellerInsight']);
});

Route::middleware('auth:sanctum')->get(
    '/ai/seller/tenun-guides',
    [TenunGuideController::class, 'index']
);

Route::middleware('auth:sanctum')->get(
    '/ai/seller/tenun-guides/{id}',
    [TenunGuideController::class, 'show']
);

Route::middleware('auth:sanctum')->get(
    '/ai/seller/stock-discount',
    [AiInsightController::class, 'stockAndDiscountInsight']
);

Route::middleware(['auth:sanctum'])->post(
    '/ai/seller/tenun-guide',
    [AiInsightController::class, 'tenunGuide']
);

// Route::middleware('auth:sanctum')->get('/ai/seller/design-preview', [AiInsightController::class, 'designPreview']);

Route::middleware('auth:sanctum')->post(
    '/ai/recommendation/{id}/approve',
    [AiRecommendationController::class, 'approve']
);

Route::middleware('auth:sanctum')->get(
    '/ai/seller/health-score',
    [AiInsightController::class, 'productHealthScore']
);

Route::patch('/admin/ai-insight/{id}/score', function (
    Request $request,
    $id
) {
    $log = AiInsightLog::findOrFail($id);

    $log->update([
        'manual_score' => $request->score,
        'manual_note'  => $request->note,
    ]);

    return response()->json(['success' => true]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/conversations', [MessageController::class, 'index']);
    Route::post('/conversations/{id}', [MessageController::class, 'getOrCreateConversation']);
    Route::get('/conversations/{conversationId}/messages', [MessageController::class, 'getMessages']);
    Route::post('/messages/{conversationId}', [MessageController::class, 'sendMessage']);

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/send-token', [AuthController::class, 'sendToken']);
    Route::post('/auth/email-verify', [AuthController::class, 'verifyEmail']);

    Route::delete('/product/delete/{id}', [ProductController::class, 'delete'])->middleware('role:admin,artisan');

    Route::get('/order/show/{id}', [OrderController::class, 'show']);

    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::post('/user/profile/update', [UserController::class, 'update']);
    Route::put('/user/change-password', [UserController::class, 'changePassword']);

    Route::get('/order/detail/{id}', [OrderController::class, 'detailOrder']);

    Route::middleware('role:customer')->group(function () {
        Route::post('/cart/store/{id}', [OrderController::class, 'cart']);
        Route::get('/cart/get', [OrderController::class, 'myCart']);
        Route::put('/cart/editCart/{id}', [OrderController::class, 'editCart']);
        Route::delete('/cart/delete/{id}', [OrderController::class, 'deleteCart']);
        Route::get('/cart/count', [OrderController::class, 'cartCount']);

        Route::post('/order/cart', [OrderController::class, 'orderCart']);
        Route::post('/order/direct/{id}', [OrderController::class, 'directOrder']);
        Route::get('/order/myorder', [OrderController::class, 'myOrder']);

        Route::post('/payment/{id}', [PaymentController::class, 'pay']);

        Route::post('/reviews/{orderItem}', [ReviewController::class,'store']);
        Route::put('/reviews/{review}', [ReviewController::class,'update']);
        
        Route::get('/address', [ShippingAddressController::class, 'index']);
        Route::post('/address/store', [ShippingAddressController::class, 'store']);
        Route::put('/address/update/{id}', [ShippingAddressController::class, 'update']);
        Route::delete('/address/delete/{id}', [ShippingAddressController::class, 'delete']);
        Route::get('/address/{id}', [ShippingAddressController::class, 'show']);
        
        Route::post('/order-items/{id}/confirm', [OrderItemController::class, 'confirmReceived']);
        Route::post('/cancel/order/{id}', [CancelController::class, 'cancelOrder']);
        Route::post('/cancel/{orderItemId}', [CancelController::class, 'request']);
    });
        
    Route::middleware('role:artisan')->group(function () {
        Route::get('/product/my', [ProductController::class, 'myProduct']);
        Route::post('/product/store', [ProductController::class, 'store']);
        Route::post('/update/product/{id}', [ProductController::class, 'update']);
        Route::delete('/product/delete/{id}', [ProductController::class, 'delete']);
        
        Route::get('/order/in', [OrderController::class, 'orderIn']);
        Route::get('/order/in/newer', [OrderController::class, 'orderInNewer']);
        Route::get('/order/total', [OrderController::class, 'totalTransaction']);
        
        Route::put('/order-items/{id}/status', [OrderItemController::class, 'updateStatus']);

        Route::post('/cancel/{id}/seller-approve', [CancelController::class, 'sellerApprove']);
        
        Route::post('/withdraw/request', [WithdrawController::class, 'requestWithdraw']);
        Route::get('/wallet/info', [WithdrawController::class, 'balance']);
    });
        
    Route::middleware('role:admin')->group(function () {
        Route::get('/order', [OrderController::class, 'order']);
        Route::get('/admin/dashboard-stats', [OrderController::class, 'adminDashboardStats']);
        
        Route::get('/admin/totalisArtisan', [AdminController::class, 'totalPendaftaran']);
        Route::get('/admin/artisan/list', [AdminController::class, 'listPendaftaran']);
        Route::put('/admin/confirm/{id}', [AdminController::class, 'confirm']);
        Route::get('/admin/total/artisan/active', [AdminController::class, 'totalActiveArtisan']);
        Route::get('/admin/listActiveArtisan', [AdminController::class, 'listActiveArtisan']);
        Route::get('/admin/commision', [AdminController::class, 'commision']);
        Route::put('/admin/deactive/{id}', [AdminController::class, 'confirm']);
        Route::post('/cancel/{id}/admin-approve', [CancelController::class, 'adminApprove']);
        Route::get('/admin/order/on-progress', [AdminController::class, 'orderOnProgress']);

        Route::post('/withdraw/approve/{id}', [WithdrawController::class, 'approve']);
        Route::post('/withdraw/markpaid/{id}', [WithdrawController::class, 'markPaid']);
    });
});

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::post('/midtrans/callback', [MidtransCallbackController::class, 'handle'])->middleware('throttle:20,1');;

Route::get('/artisan/shop/{id}', [UserController::class, 'artisanShop']);
Route::get('/product', [ProductController::class, 'index']);
Route::get('/product/songket', [ProductController::class, 'songket']);
Route::get('/product/endek', [ProductController::class, 'endek']);
Route::get('/product/{id}', [ProductController::class, 'show']);

Route::get('/products/{product}/reviews', [ReviewController::class,'reviewProduct']);
Route::get('/artisan/review/{id}', [ReviewController::class, 'showTotalReviews']);
Route::post('/auth/forget-password', [AuthController::class, 'forgetPassword']);
