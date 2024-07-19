<?php

use FriendsOfBotble\Instamojo\Http\Controllers\InstamojoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['core'])->prefix('payment/instamojo')->name('payment.instamojo.')->group(function () {
    Route::get('callback', [InstamojoController::class, 'callback'])->name('callback');
});
