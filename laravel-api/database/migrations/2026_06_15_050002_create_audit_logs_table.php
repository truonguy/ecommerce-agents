<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('guard');
            $table->string('email')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('action');
            $table->string('result');
            $table->timestamp('created_at')->nullable();

            $table->index(['guard', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
