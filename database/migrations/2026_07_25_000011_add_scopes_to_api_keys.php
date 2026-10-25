<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->json('scopes')->nullable()->after('name');
        });

        DB::table('api_keys')
            ->whereNull('scopes')
            ->update(['scopes' => json_encode(['media:import'], JSON_THROW_ON_ERROR)]);
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->dropColumn('scopes');
        });
    }
};
