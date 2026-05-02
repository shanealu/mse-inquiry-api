<?php

namespace Database\Seeders;

use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use App\Models\Inquiry;
use Illuminate\Database\Seeder;

class InquirySeeder extends Seeder
{
    public function run(): void
    {
        $this->createBatch(5, InquiryType::Trading, InquiryStatus::New);
        $this->createBatch(5, InquiryType::MarketData, InquiryStatus::New);
        $this->createBatch(5, InquiryType::TechnicalIssue, InquiryStatus::New);
        $this->createBatch(5, InquiryType::GeneralQuestion, InquiryStatus::New);

        $this->createBatch(3, InquiryType::Trading, InquiryStatus::InProgress);
        $this->createBatch(2, InquiryType::TechnicalIssue, InquiryStatus::Resolved);
        $this->createBatch(1, InquiryType::GeneralQuestion, InquiryStatus::Closed);
    }

    private function createBatch(int $count, InquiryType $type, InquiryStatus $status): void
    {
        Inquiry::factory()
            ->count($count)
            ->ofType($type)
            ->withStatus($status)
            ->create()
            ->each(function (Inquiry $inquiry) {
                $inquiry->reference_number = Inquiry::formatReference(
                    $inquiry->created_at->year,
                    $inquiry->id,
                );
                $inquiry->save();
            });
    }
}
