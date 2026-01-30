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

        Route::post('/review/store/{id}', [ReviewController::class, 'store']);
        Route::post('/review/update/{id}', [ReviewController::class, 'update']);

        Route::get('/address', [ShippingAddressController::class, 'index']);
        Route::post('/address/store', [ShippingAddressController::class, 'store']);
        Route::put('/address/update/{id}', [ShippingAddressController::class, 'update']);
        Route::delete('/address/delete/{id}', [ShippingAddressController::class, 'delete']);
        Route::get('/address/{id}', [ShippingAddressController::class, 'show']);
    });

    Route::middleware('role:artisan')->group(function () {
        Route::get('/product/my', [ProductController::class, 'myProduct']);
        Route::post('/product/store', [ProductController::class, 'store']);
        Route::post('/update/product/{id}', [ProductController::class, 'update']);
        Route::delete('/product/delete/{id}', [ProductController::class, 'delete']);

        Route::get('/order/in', [OrderController::class, 'orderIn']);
        Route::put('/order/update-status/{id}', [OrderController::class, 'updateStatus']);

        Route::get('/saldo/get', [TransactionController::class, 'saldoUser']);
        Route::get('/commision/get', [TransactionController::class, 'commision']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/order', [OrderController::class, 'order']);
        Route::get('/admin/dashboard-stats', [OrderController::class, 'adminDashboardStats']);
    });
});

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::post('/midtrans/callback', [MidtransCallbackController::class, 'handle']);

Route::get('/product', [ProductController::class, 'index']);
Route::get('/product/songket', [ProductController::class, 'songket']);
Route::get('/product/endek', [ProductController::class, 'endek']);
Route::get('/product/{id}', [ProductController::class, 'show']);

Route::get('/review/product/{id}', [ReviewController::class, 'reviewProduct']);
Route::get('/artisan/shop/{id}', [UserController::class, 'artisanShop']);
