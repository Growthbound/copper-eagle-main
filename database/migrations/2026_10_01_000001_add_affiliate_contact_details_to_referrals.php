<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->json('service_areas')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['phone', 'email', 'service_areas'] as $column) {
            Schema::table('referrals', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }
};
