<?php

namespace Database\Factories;

use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use App\Models\Inquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $submittedAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'reference_number' => null,
            'type' => fake()->randomElement(InquiryType::cases()),
            'subject' => fake()->sentence(6),
            'message' => fake()->paragraph(3),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional()->numerify('+9607#######'),
            'status' => InquiryStatus::New,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'submitted_at' => $submittedAt,
        ];
    }

    public function ofType(InquiryType $type): self
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function withStatus(InquiryStatus $status): self
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
