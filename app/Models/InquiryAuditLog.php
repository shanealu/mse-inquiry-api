<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryAuditLog extends Model
{
    // Audit rows are immutable — only created_at is tracked.
    public $timestamps = false;

    protected $fillable = [
        'inquiry_id',
        'action',
        'context',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }
}
