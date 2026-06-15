<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'employees'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedInteger('failed_login_attempts')->default(0)->after('status');
            });
        }
    }

    public function down(): void
    {
        foreach (['customers', 'employees'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('failed_login_attempts');
            });
        }
    }
};
