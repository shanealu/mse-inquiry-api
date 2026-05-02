<?php

namespace App\Http\Resources;

use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Inquiry
 */
class InquirySummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'subject' => $this->subject,
            'name' => $this->name,
            'email' => $this->email,
            'submitted_at' => $this->submitted_at?->toIso8601ZuluString(),
        ];
    }
}
