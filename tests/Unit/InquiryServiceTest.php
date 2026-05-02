<?php

use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use App\Models\Inquiry;
use App\Services\InquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates reference number from year and zero-padded id', function () {
    $service = app(InquiryService::class);

    $inquiry = $service->store([
        'type' => 'trading',
        'subject' => 'Hello there',
        'message' => 'A message of sufficient length',
        'name' => 'Sam',
        'email' => 'sam@example.com',
    ], '127.0.0.1', 'pest');

    expect($inquiry->reference_number)
        ->toMatch('/^INQ-\d{4}-\d{6}$/')
        ->toBe(sprintf('INQ-%d-%06d', $inquiry->created_at->year, $inquiry->id));
});

it('round-trips enum casts on the model', function () {
    $inquiry = Inquiry::factory()->create([
        'type' => InquiryType::MarketData,
        'status' => InquiryStatus::Resolved,
    ]);

    $fresh = $inquiry->fresh();
    expect($fresh->type)->toBe(InquiryType::MarketData);
    expect($fresh->status)->toBe(InquiryStatus::Resolved);
});
