<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')
                ->constrained('inquiries')
                ->cascadeOnDelete();
            $table->string('action', 50);
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            // Audit logs are immutable — created_at only, no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inquiry_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_audit_logs');
    }
};
