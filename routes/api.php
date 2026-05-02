<?php

use App\Http\Controllers\Api\V1\InquiryController;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Custom binder: numeric → id, otherwise → reference_number.
// Returns 404 (not 500) when the value resolves to no row.
Route::bind('inquiry', function (string $value): Inquiry {
    $query = Inquiry::query();
    $inquiry = ctype_digit($value)
        ? $query->find($value)
        : $query->where('reference_number', $value)->first();

    if (! $inquiry) {
        throw new NotFoundHttpException('Inquiry not found.');
    }

    return $inquiry;
});

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::post('inquiries', [InquiryController::class, 'store']);
    Route::get('inquiries', [InquiryController::class, 'index']);
    Route::get('inquiries/{inquiry}', [InquiryController::class, 'show']);
});
