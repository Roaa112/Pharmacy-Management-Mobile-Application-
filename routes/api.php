<?php

use Illuminate\Http\Request;
use App\Modules\Cart\CartController;
use App\Modules\User\UserController;
use Illuminate\Support\Facades\Route;
use App\Modules\User\ApiUserController;
use App\Modules\Brand\ApiBrandController;
use App\Modules\Banner\ApiBannerController;
use App\Modules\Favorite\FavoriteController;
use App\Modules\Location\LocationController;
use App\Modules\Address\ApiAddressController;
use App\Modules\Contact\ApiContactController;
use App\Modules\Developer\ProjectsController;
use App\Modules\Product\ApiProductController;
use App\Modules\Rate\ProductReviewController;
use App\Modules\Setting\ApiSettingController;
use App\Modules\Category\ApiCategoryController;
use App\Modules\Medication\MedicationController;
use App\Modules\Developer\ProjectExportController;
use App\Modules\HealthIssue\ApiHealthIssueController;
use App\Modules\ContactMessage\ApiContactMassageController;
use App\Modules\ContactMessage\ApiContactMessageController;

Route::middleware(['setLocaleLang'])->get('/test-json', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'This is a valid JSON response',
        'data' => [
            'current_locale' => app()->getLocale(),
            'is_arabic' => (app()->getLocale() === 'ar')
        ]
    ]);
});
 Route::middleware('auth:sanctum')->get('/token/check', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Token is valid',
        'user' => auth()->user()
    ]);
});
Route::middleware([\App\Http\Middleware\SetLocaleLang::class])
    ->prefix('v1') 
    ->group(function () {
        Route::prefix('user')->group(function () { 
    Route::get('/products/{product}/related', [ApiProductController::class, 'relatedProducts']);
   Route::get('brands',[ApiBrandController::class,'listAllBrands'] );
    Route::get('/brands/{id}/products', [ApiBrandController::class, 'listAllBrandsProducts']);
    Route::get('categories',[ApiCategoryController::class,'listAllCategories'] );
    Route::get('categories/{id}/products',[ApiCategoryController::class,'listAllCategoriesProducts'] );
    Route::get('/categories/{id}/products/search', [ApiCategoryController::class, 'searchCategoryProducts']);
    Route::get('settings',[ApiSettingController::class,'listAllsettings'] );
    Route::get('health_issues',[ApiHealthIssueController::class,'listAllHealthIssues'] );
           Route::get('health_issues/{id}/products',[ApiHealthIssueController::class,'listAllHealthIssuesProducts'] );
    Route::get('banners', [ApiBannerController::class, 'listAllBanners']);
    Route::get('products',[ApiProductController::class,'listAllProducts'] );
    Route::get('show/{id}/product',[ApiProductController::class,'showProduct'] );
    Route::get('products/on/sale',[ApiProductController::class,'listAllProductsOnSale'] );
    Route::get('/products/search', [ApiProductController::class, 'searchProducts']);
    Route::get('latest-products', [ApiProductController::class, 'latestProducts']);
    Route::get('top-discount-products', [ApiProductController::class, 'topDiscountProducts']);
    //fake
    Route::get('Best-selling-products', [ApiProductController::class, 'listAllProducts']);

        });  // <-- هذه مغلقة بشكل صحيح
    }); 
Route::prefix('user')->group(function () {  
    Route::post('register', [UserController::class, 'createUser']);
    Route::post('verify', [UserController::class, 'verify']);
    Route::post('login', [UserController::class, 'login']);

   
    Route::post('password/forgot', [UserController::class, 'sendResetOtp']);
    Route::post('password/verify-otp', [UserController::class, 'verifyOtp']);
    Route::post('password/reset', [UserController::class, 'resetPassword']);
    
});
Route::middleware('auth:sanctum')->post('logout', [UserController::class, 'logout']);  
Route::middleware(['auth:sanctum'])->middleware(['auth:sanctum', \App\Http\Middleware\SetLocaleLang::class])->get('user/favorites', [FavoriteController::class, 'list']);
Route::middleware(['auth:sanctum'])->post('user/favorites/{productId}', [FavoriteController::class, 'toggle']);
Route::middleware(['auth:sanctum'])->post('user/products/{product}/rate', [ProductReviewController::class, 'store']);
Route::middleware(['auth:sanctum'])->get('user/products/{product}/reviews', [ProductReviewController::class, 'index']);






Route::middleware(['auth:sanctum', \App\Http\Middleware\SetLocaleLang::class])
    ->prefix('user')
    ->group(function () {   

    Route::get('user/data',[ApiUserController::class,'getUserData'] );
   
    Route::get('addresses', [ApiAddressController::class, 'listAllAddresses'])->name('addresses.index');
    Route::get('addresses/{id}', [ApiAddressController::class, 'getAddressById'])->name('addresses.show');
    Route::post('addresses', [ApiAddressController::class, 'createAddress'])->name('addresses.store');
    Route::put('addresses/{id}', [ApiAddressController::class, 'updateAddress'])->name('addresses.update');
    Route::delete('addresses/{id}', [ApiAddressController::class, 'deleteAddress'])->name('addresses.destroy');
    Route::post('/addresses/{id}/toggle-default', [ApiAddressController::class, 'toggleDefault']);
    Route::get('/medication', [MedicationController::class, 'index']);
    Route::post('/medication', [MedicationController::class, 'store']);
    Route::put('{medication}', [MedicationController::class, 'update']);
    Route::delete('/medication/{medication}', [MedicationController::class, 'destroy']);
    Route::post('/medications/{medication}/log', [MedicationController::class, 'updateLogStatus']);
    Route::delete('/medications/remove-day', [MedicationController::class, 'deleteDayFromMedication']);

   
}); 
Route::middleware('auth:sanctum')->post('user/contact-messages', [ApiContactMassageController::class, 'store']);
Route::middleware('auth:sanctum')->prefix('user/cart')->group(function () {  
   
    Route::get('/', [CartController::class, 'showCart']); // get cart items
    Route::post('/', [CartController::class, 'addToCart']); // add or update item to cart
    Route::delete('/{id}', [CartController::class, 'removeItem']); 
    Route::post('/apply-coupon', [CartController::class, 'applyCoupon']); // apply coupon
    Route::post('/update-quantity', [CartController::class, 'updateQuantity']);
});


// Route::controller(LocationController::class)->prefix('location')->group(function () {
//     Route::get('city', 'listAllCites');
//     Route::get('area', 'listAllAreas');
// })->middleware('auth:sanctum');


