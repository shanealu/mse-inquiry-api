<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            // Nullable because we set it after the row's auto-increment id is assigned,
            // inside the same DB::transaction that creates the row.
            $table->string('reference_number', 20)->nullable()->unique();
            $table->string('type', 32);
            $table->string('subject', 200);
            $table->text('message');
            $table->string('name', 120);
            $table->string('email', 255)->index();
            $table->string('phone', 30)->nullable();
            $table->string('status', 20)->default('new');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
