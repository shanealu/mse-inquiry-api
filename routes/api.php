<?php

use App\Http\Controllers\Api\V1\InquiryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::post('inquiries', [InquiryController::class, 'store']);
    Route::get('inquiries', [InquiryController::class, 'index']);
    Route::get('inquiries/{inquiry}', [InquiryController::class, 'show']);
});
