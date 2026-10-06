<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpa_verification_progress', function (Blueprint $table): void {
            $table->string('expiration_reason', 64)->nullable()->after('last_error_code');
        });
    }

    public function down(): void
    {
        Schema::table('cpa_verification_progress', function (Blueprint $table): void {
            $table->dropColumn('expiration_reason');
        });
    }
};
