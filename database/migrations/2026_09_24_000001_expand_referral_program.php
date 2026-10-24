<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_team_members', function (Blueprint $table): void {
            $table->id();
            $table->string('highlevel_id');
            $table->string('location_id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['location_id', 'highlevel_id']);
        });
        Schema::table('referrals', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
            $table->string('business_name')->nullable()->change();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('main_contact_id')->nullable();
            $table->string('main_contact_name')->nullable();
            $table->timestamp('registered_at')->nullable()->index();
            $table->uuid('batch_id')->nullable()->index();
            $table->string('referral_url', 2048)->nullable();
            $table->unsignedBigInteger('visits')->default(0);
            $table->timestamp('last_visited_at')->nullable();
            $table->string('sync_status')->default('pending')->index();
            $table->string('sync_message')->nullable();
            $table->string('highlevel_contact_id')->nullable();
            $table->string('highlevel_location_id')->nullable();
            $table->timestamp('synced_at')->nullable();
        });
        // Preserve registrations created by the first version, including their full name.
        DB::table('referrals')->whereNotNull('name')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('referrals')->where('id', $row->id)->update([
                    'registered_at' => $row->created_at ?? now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Unclaimed inventory has no representation in the original schema.
        DB::table('referrals')->whereNull('registered_at')->delete();
        Schema::table('referrals', function (Blueprint $table): void {
            $table->dropIndex(['registered_at']);
            $table->dropIndex(['batch_id']);
            $table->dropIndex(['sync_status']);
            $table->dropColumn(['first_name', 'last_name', 'main_contact_id', 'main_contact_name', 'registered_at', 'batch_id', 'referral_url', 'visits', 'last_visited_at', 'sync_status', 'sync_message', 'highlevel_contact_id', 'highlevel_location_id', 'synced_at']);
            $table->string('name')->nullable(false)->change();
            $table->string('business_name')->nullable(false)->change();
        });
        Schema::dropIfExists('referral_team_members');
    }
};
