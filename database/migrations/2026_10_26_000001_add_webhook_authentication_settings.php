<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table): void {
            $table->string('authentication_mode', 20)->default('hmac');
            $table->string('header_name', 80)->default('X-Site-Signature');
            $table->text('header_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table): void {
            $table->dropColumn(['authentication_mode', 'header_name', 'header_secret']);
        });
    }
};
