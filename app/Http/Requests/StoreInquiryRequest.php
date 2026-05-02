<?php

namespace App\Http\Requests;

use App\Enums\InquiryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInquiryRequest extends FormRequest
{
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
            'type' => ['required', 'string', Rule::enum(InquiryType::class)],
            'subject' => ['required', 'string', 'min:5', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[+0-9 \-()]{7,30}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $allowedTypes = collect(InquiryType::cases())
            ->map(fn (InquiryType $t) => $t->value)
            ->implode(', ');

        return [
            'type.enum' => "The inquiry type must be one of: {$allowedTypes}.",
            'subject.min' => 'The subject must be at least 5 characters.',
            'subject.max' => 'The subject may not exceed 200 characters.',
            'message.min' => 'Please provide a message of at least 10 characters.',
            'message.max' => 'The message may not exceed 5000 characters.',
            'email.email' => 'Please provide a valid email address.',
            'phone.regex' => 'The phone number format is invalid.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'inquiry type',
            'subject' => 'subject',
            'message' => 'message',
            'name' => 'name',
            'email' => 'email address',
            'phone' => 'phone number',
        ];
    }
}
