<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rbac_user_permission_snapshots', function (Blueprint $table): void {
            $table->json('roles')->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('rbac_user_permission_snapshots', function (Blueprint $table): void {
            $table->dropColumn('roles');
        });
    }
};
