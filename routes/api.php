<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\MOQController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UsersController;
use App\Http\Middleware\JwtMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::controller(AuthController::class)->group(function () {
    Route::get('verify-email/{id}/{hash}', 'verifyEmail')
        ->name('verification.verify')
        ->middleware('signed');
    Route::post('login', 'login');
    Route::post('register', 'register');
    Route::post('resendVerificationEmail','resendVerificationEmail');

    Route::get('categories/open', [CategoriesController::class, 'allCategories']);
    Route::get('products/open', [ProductController::class, 'allProductsOpen']);
    Route::get('settings/open', [SettingsController::class, 'index']);
    Route::get('orders/open', [OrdersController::class, 'allOrders']);
    Route::post('orders/open/create', [OrdersController::class, 'createOrder']);
    Route::get('products/open/{id}', [ProductController::class, 'getProduct']);

    // Reviews
    Route::get('reviews', [ReviewController::class, 'allReviews']);
    Route::post('reviews/create', [ReviewController::class, 'createReview']);
    Route::put('reviews/update', [ReviewController::class, 'updateReview']);

    Route::get('settings', [SettingsController::class, 'index']);


});

Route::middleware([JwtMiddleware::class])->group(function () {
    // Auth
    Route::post('logout', [AuthController::class, 'logout']);

    // Users
    Route::get('users', [UsersController::class, 'allUsers']);
    Route::get('users/me', [UsersController::class, 'getByUuid']);
    Route::post('users/create', [UsersController::class, 'create']);
    Route::put('users/update', [UsersController::class, 'update']);
    Route::delete('users/delete', [UsersController::class, 'delete']);
    Route::put('users/activate', [UsersController::class, 'activate_deactivate']);
    Route::put('users/deactivate', [UsersController::class, 'activate_deactivate']);

    // Roles
    Route::get('roles', [RolesController::class, 'allRoles']);
    Route::post('roles/create', [RolesController::class, 'createRole']);
    Route::put('roles/update', [RolesController::class, 'updateRole']);
    Route::delete('roles/delete', [RolesController::class, 'deleteRole']);

    // Categories
    Route::get('categories', [CategoriesController::class, 'allCategories']);
    Route::post('categories/create', [CategoriesController::class, 'createCategory']);
    Route::post('categories/update', [CategoriesController::class, 'updateCategory']);
    Route::post('categories/delete', [CategoriesController::class, 'deleteCategory']);
    Route::post('categories/restore', [CategoriesController::class, 'restoreCategory']);

    // Products
    Route::get('products', [ProductController::class, 'allProducts']);
    Route::get('products/{id}', [ProductController::class, 'getProduct']);
    Route::post('products/create', [ProductController::class, 'createProduct']);
    Route::post('products/update', [ProductController::class, 'updateProduct']);
    Route::post('products/delete', [ProductController::class, 'deleteProduct']);
    Route::post('products/restore', [ProductController::class, 'restoreProduct']);

    // MOQ
    Route::get('moq/products', [MoqController::class, 'moqProducts']);
    Route::get('moq/stats', [MoqController::class, 'moqStats']);
    Route::post('moq/create', [MoqController::class, 'createMOQ']);
    Route::post('moq/close', [MoqController::class, 'closeMOQ']);
    Route::post('moq/closeShippingFeeCollection', [MoqController::class, 'closeShippingFeeCollection']);
    Route::post('moq/updateOrderBatchStatus', [MoqController::class, 'updateOrderBatchStatus']);

    // Settings

    Route::post('settings/create', [SettingsController::class, 'create']);

    // Orders
    Route::get('orders', [OrdersController::class, 'allOrders']);
    Route::get('orders/{id}', [OrdersController::class, 'singleOrder']);
    Route::post('orders/create', [OrdersController::class, 'createOrder']);
    Route::put('orders/updateOrderStatus', [OrdersController::class, 'updateOrderStatus']);

    // Payments
    Route::get('payments', [PaymentController::class, 'allPayments']);
    Route::post('payments/create', [PaymentController::class, 'createPayment']);
    Route::post('payments/updatePaymentStatus', [PaymentController::class, 'updatePaymentStatus']);

});
