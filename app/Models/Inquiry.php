<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number',
        'type',
        'subject',
        'message',
        'name',
        'email',
        'phone',
        'status',
        'ip_address',
        'user_agent',
        'submitted_at',
    ];

    protected $casts = [
        'type' => InquiryType::class,
        'status' => InquiryStatus::class,
        'submitted_at' => 'datetime',
    ];

    public function auditLogs(): HasMany
    {
        return $this->hasMany(InquiryAuditLog::class);
    }

    public function scopeOfType(Builder $query, InquiryType|string $type): Builder
    {
        return $query->where('type', $type instanceof InquiryType ? $type->value : $type);
    }

    public function scopeOfStatus(Builder $query, InquiryStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof InquiryStatus ? $status->value : $status);
    }

    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    public function scopeCreatedFrom(Builder $query, string $from): Builder
    {
        return $query->where('created_at', '>=', $from);
    }

    public function scopeCreatedTo(Builder $query, string $to): Builder
    {
        return $query->where('created_at', '<=', $to);
    }
}
