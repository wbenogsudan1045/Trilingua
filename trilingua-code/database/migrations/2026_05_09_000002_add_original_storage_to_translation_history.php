<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('translation_history', function (Blueprint $table) {
            $table->text('original_storage_path')->nullable()->after('storage_path');
            $table->timestampTz('original_signed_url_expires_at')->nullable()->after('original_storage_path');
        });
    }

    public function down(): void
    {
        Schema::table('translation_history', function (Blueprint $table) {
            $table->dropColumn(['original_storage_path', 'original_signed_url_expires_at']);
        });
    }
};
