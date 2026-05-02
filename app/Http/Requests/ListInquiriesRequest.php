<?php

namespace App\Http\Requests;

use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInquiriesRequest extends FormRequest
{
    private const ALLOWED_PARAMS = [
        'type', 'status', 'email', 'from', 'to', 'per_page', 'page', 'sort',
    ];

    private const ALLOWED_SORTS = [
        'created_at', '-created_at', 'submitted_at', '-submitted_at',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::enum(InquiryType::class)],
            'status' => ['sometimes', 'string', Rule::enum(InquiryStatus::class)],
            'email' => ['sometimes', 'email:rfc', 'max:255'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', 'string', Rule::in(self::ALLOWED_SORTS)],
        ];
    }

    /**
     * Reject unknown query parameters — surfaces client bugs early (spec §5.3).
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $unknown = array_diff(array_keys($this->query()), self::ALLOWED_PARAMS);

        if (! empty($unknown)) {
            $validator->after(function ($v) use ($unknown) {
                $v->errors()->add(
                    'query',
                    'Unknown query parameter(s): '.implode(', ', $unknown).'.',
                );
            });
        }
    }

    public function perPage(): int
    {
        return (int) $this->query('per_page', 15);
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public function sortColumnAndDirection(): array
    {
        $sort = (string) $this->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        return [$column, $direction];
    }
}
