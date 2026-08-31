<?php

/*
| Shop — the club admin's side.
|
| Registered by App\Support\Modules\ModuleServiceProvider under
| admin/club/{club} with the `admin.club.` name prefix and the
| ['web','auth','verified','two-factor','tenant','throttle:admin-write'] stack —
| the same group these routes were declared in when they lived in
| routes/web.php, so every URL and route name is unchanged.
*/

use App\Shop\Controllers\ClubOrderController;
use App\Shop\Controllers\ClubPerkController;
use App\Shop\Controllers\ClubShopController;
use Illuminate\Support\Facades\Route;

// Products and their categories.
Route::get('/shop', [ClubShopController::class, 'shop'])->name('shop');
Route::post('/shop/products', [ClubShopController::class, 'storeProduct'])->name('shop.products.store');
Route::put('/shop/products/{product}', [ClubShopController::class, 'updateProduct'])->name('shop.products.update');
Route::delete('/shop/products/{product}', [ClubShopController::class, 'destroyProduct'])->name('shop.products.destroy');
Route::post('/shop/products/{product}/stock-mute', [ClubShopController::class, 'muteStockAlert'])->name('shop.products.stock-mute');
Route::post('/shop/categories', [ClubShopController::class, 'storeCategory'])->name('shop.categories.store');
Route::put('/shop/categories/{category}', [ClubShopController::class, 'updateCategory'])->name('shop.categories.update');
Route::delete('/shop/categories/{category}', [ClubShopController::class, 'destroyCategory'])->name('shop.categories.destroy');

// Orders members placed.
Route::get('/orders', [ClubOrderController::class, 'index'])->name('orders');
Route::patch('/orders/{order}/status', [ClubOrderController::class, 'updateStatus'])->name('orders.status');

// Perks members redeem.
Route::get('/perks', [ClubPerkController::class, 'perks'])->name('perks');
Route::post('/perks', [ClubPerkController::class, 'storePerk'])->name('perks.store');
Route::put('/perks/{perk}', [ClubPerkController::class, 'updatePerk'])->name('perks.update');
Route::delete('/perks/{perk}', [ClubPerkController::class, 'destroyPerk'])->name('perks.destroy');
