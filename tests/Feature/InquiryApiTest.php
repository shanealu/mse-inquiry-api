<?php

use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use App\Models\Inquiry;
use App\Models\InquiryAuditLog;
use App\Services\AuditLogger;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validInquiryPayload(array $overrides = []): array
{
    return [
        'type' => 'trading',
        'subject' => 'Hello there',
        'message' => 'A message of sufficient length',
        'name' => 'Sam',
        'email' => 'sam@example.com',
        ...$overrides,
    ];
}

it('creates an inquiry and writes a created audit row', function () {
    $payload = validInquiryPayload([
        'subject' => 'Question about T+2 settlement',
        'message' => 'I would like to understand how settlement works for cross-listed shares.',
        'name' => 'Ahmed Shaneel',
        'email' => 'ahmed@example.com',
        'phone' => '+9607123456',
    ]);

    postJson('/api/v1/inquiries', $payload)
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id', 'reference_number', 'type', 'status', 'subject', 'submitted_at',
            ],
        ])
        ->assertJsonPath('data.type', 'trading')
        ->assertJsonPath('data.status', 'new');

    $inquiry = Inquiry::sole();
    expect($inquiry->reference_number)->toMatch('/^INQ-\d{4}-\d{6}$/');
    expect($inquiry->ip_address)->not->toBeNull();
    expect($inquiry->submitted_at)->not->toBeNull();

    expect(InquiryAuditLog::where('inquiry_id', $inquiry->id)->where('action', 'created')->exists())
        ->toBeTrue();
});

it('rejects invalid create payloads', function (array $payload, array $expectedErrorFields) {
    postJson('/api/v1/inquiries', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($expectedErrorFields);
})->with([
    'missing all' => [[], ['type', 'subject', 'message', 'name', 'email']],
    'invalid type' => [validInquiryPayload(['type' => 'invalid']), ['type']],
    'short subject' => [validInquiryPayload(['subject' => 'Hi']), ['subject']],
    'short message' => [validInquiryPayload(['message' => 'short']), ['message']],
    'invalid email' => [validInquiryPayload(['email' => 'not-an-email']), ['email']],
    'invalid phone' => [validInquiryPayload(['phone' => 'abc']), ['phone']],
]);

it('does not accept ip_address, user_agent, or submitted_at from the client', function () {
    $payload = validInquiryPayload([
        'ip_address' => '9.9.9.9',
        'user_agent' => 'fake-agent',
        'submitted_at' => '2000-01-01T00:00:00Z',
    ]);

    postJson('/api/v1/inquiries', $payload, ['User-Agent' => 'real-agent'])->assertCreated();

    $inquiry = Inquiry::sole();
    expect($inquiry->ip_address)->not->toBe('9.9.9.9');
    expect($inquiry->user_agent)->toBe('real-agent');
    expect($inquiry->submitted_at->year)->toBe((int) date('Y'));
});

it('rolls back the inquiry insert if the audit logger fails', function () {
    $this->mock(AuditLogger::class, function ($mock) {
        $mock->shouldReceive('log')->andThrow(new RuntimeException('audit broken'));
    });

    $this->withoutExceptionHandling([RuntimeException::class]);

    try {
        postJson('/api/v1/inquiries', validInquiryPayload());
        $this->fail('Expected RuntimeException to bubble up.');
    } catch (RuntimeException $e) {
        // expected
    }

    expect(Inquiry::count())->toBe(0);
    expect(InquiryAuditLog::count())->toBe(0);
});

it('lists paginated inquiries', function () {
    Inquiry::factory()->count(3)->create();

    getJson('/api/v1/inquiries')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'reference_number', 'type', 'status', 'subject', 'name', 'email', 'submitted_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'links' => ['first', 'last', 'prev', 'next'],
        ])
        ->assertJsonMissingPath('data.0.message');
});

it('filters list by type', function () {
    Inquiry::factory()->ofType(InquiryType::Trading)->count(2)->create();
    Inquiry::factory()->ofType(InquiryType::TechnicalIssue)->count(3)->create();

    getJson('/api/v1/inquiries?type=trading')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('filters list by email', function () {
    Inquiry::factory()->create(['email' => 'target@example.com']);
    Inquiry::factory()->count(2)->create();

    getJson('/api/v1/inquiries?email=target@example.com')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('caps per_page at 100', function () {
    getJson('/api/v1/inquiries?per_page=200')->assertUnprocessable();
});

it('rejects unknown query parameters', function () {
    getJson('/api/v1/inquiries?bogus=1')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['query']);
});

it('shows an inquiry by numeric id and writes a viewed audit row', function () {
    $inquiry = Inquiry::factory()->create(['reference_number' => 'INQ-2026-000001']);

    getJson("/api/v1/inquiries/{$inquiry->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.reference_number', 'INQ-2026-000001');

    expect(InquiryAuditLog::where('inquiry_id', $inquiry->id)->where('action', 'viewed')->exists())
        ->toBeTrue();
});

it('shows an inquiry by reference number', function () {
    $inquiry = Inquiry::factory()->create(['reference_number' => 'INQ-2026-000042']);

    getJson('/api/v1/inquiries/INQ-2026-000042')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $inquiry->id);
});

it('returns 404 for unknown numeric id and does not write an audit row', function () {
    getJson('/api/v1/inquiries/999999')
        ->assertNotFound()
        ->assertJsonPath('message', 'Inquiry not found.');

    expect(InquiryAuditLog::count())->toBe(0);
});

it('returns 404 for unknown reference number', function () {
    getJson('/api/v1/inquiries/INQ-2026-NOPE')
        ->assertNotFound()
        ->assertJsonPath('message', 'Inquiry not found.');
});

it('returns 404 for soft-deleted inquiries', function () {
    $inquiry = Inquiry::factory()->create();
    $inquiry->delete();

    getJson("/api/v1/inquiries/{$inquiry->id}")->assertNotFound();
});

it('does not allow the client to set the status field (server controls it)', function () {
    postJson('/api/v1/inquiries', validInquiryPayload(['status' => 'closed']))->assertCreated();

    expect(Inquiry::sole()->status)->toBe(InquiryStatus::New);
});
