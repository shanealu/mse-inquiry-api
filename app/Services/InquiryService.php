<?php

namespace App\Services;

use App\Enums\AuditAction;
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

            $inquiry->reference_number = Inquiry::formatReference($inquiry->created_at->year, $inquiry->id);
            $inquiry->save();

            $this->auditLogger->log(
                inquiry: $inquiry,
                action: AuditAction::Created,
                context: ['type' => $inquiry->type->value],
                ip: $ip,
                userAgent: $userAgent,
            );

            return $inquiry;
        });
    }

    public function recordView(Inquiry $inquiry, ?string $ip = null, ?string $userAgent = null): void
    {
        $this->auditLogger->log(
            inquiry: $inquiry,
            action: AuditAction::Viewed,
            ip: $ip,
            userAgent: $userAgent,
        );
    }
}
