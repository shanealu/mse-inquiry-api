<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\InquiryAuditLog;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function log(
        Inquiry $inquiry,
        string $action,
        ?array $context = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): InquiryAuditLog {
        return InquiryAuditLog::create([
            'inquiry_id' => $inquiry->id,
            'action' => $action,
            'context' => $context,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
