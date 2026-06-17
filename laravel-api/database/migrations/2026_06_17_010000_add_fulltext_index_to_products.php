<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fulltext index cho keyword search (name, description). Hiện search dùng LIKE để chính xác
     * substring; index này sẵn sàng cho whereFullText khi dataset lớn (spec §9.8).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->fullText(['name', 'description']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropFullText(['name', 'description']);
        });
    }
};
