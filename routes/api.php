<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/product', [ProductController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::delete('/product/delete/{id}', [ProductController::class, 'delete'])->middleware('role:admin,pengerajin');

    Route::middleware('role:pengguna')->group(function () {
        Route::post('/cart/store/{id}', [OrderController::class, 'cart']);
        Route::post('/cart/get', [OrderController::class, 'myCart']);
    });

    Route::middleware('role:pengerajin')->group(function () {
        Route::post('/product/store', [ProductController::class, 'store']);
        Route::post('/product/update/{id}', [ProductController::class, 'update']);
    });

    Route::middleware('role:admin')->group(function () {

    });
});