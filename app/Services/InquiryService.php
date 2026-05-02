<?php

namespace App\Services;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Illuminate\Support\Facades\DB;

class InquiryService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{type: string, subject: string, message: string, name: string, email: string, phone?: ?string}  $data
     */
    public function store(array $data, ?string $ip = null, ?string $userAgent = null): Inquiry
    {
        return DB::transaction(function () use ($data, $ip, $userAgent) {
            $inquiry = Inquiry::create([
                ...$data,
                'status' => InquiryStatus::New,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'submitted_at' => now(),
            ]);

            $inquiry->reference_number = $this->buildReference($inquiry);
            $inquiry->save();

            $this->auditLogger->log(
                inquiry: $inquiry,
                action: 'created',
                context: ['type' => $inquiry->type->value],
                ip: $ip,
                userAgent: $userAgent,
            );

            return $inquiry->refresh();
        });
    }

    public function recordView(Inquiry $inquiry, ?string $ip = null, ?string $userAgent = null): void
    {
        $this->auditLogger->log(
            inquiry: $inquiry,
            action: 'viewed',
            ip: $ip,
            userAgent: $userAgent,
        );
    }

    /**
     * Format: INQ-{YEAR}-{6-digit zero-padded id}.
     * Sequence is tied to the global auto-increment id, not per-year.
     * If per-year reset is required, switch to a counters table with row-level lock.
     */
    private function buildReference(Inquiry $inquiry): string
    {
        return sprintf('INQ-%d-%06d', $inquiry->created_at->year, $inquiry->id);
    }
}
