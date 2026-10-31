<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WebhookAuthenticationMigrationTest extends TestCase
{
    public function test_existing_destinations_keep_their_secret_and_signed_delivery_defaults(): void
    {
        $this->createLegacySchema();
        $id = DB::table('webhooks')->insertGetId([
            'name' => 'Existing CRM', 'url' => 'https://crm.example/hook', 'secret' => 'whsec_example_existing',
        ]);

        $migration = require database_path('migrations/2026_10_26_000001_add_webhook_authentication_settings.php');
        $migration->up();

        $destination = DB::table('webhooks')->find($id);
        $this->assertSame('hmac', $destination->authentication_mode);
        $this->assertSame('X-Site-Signature', $destination->header_name);
        $this->assertNull($destination->header_secret);
        $this->assertSame('whsec_example_existing', $destination->secret);
    }

    public function test_rollback_removes_only_the_new_columns_and_preserves_existing_data(): void
    {
        $this->createLegacySchema();
        Schema::create('unrelated_example', fn (Blueprint $table) => $table->id());
        $id = DB::table('webhooks')->insertGetId([
            'name' => 'Existing CRM', 'url' => 'https://crm.example/hook', 'secret' => 'whsec_example_existing',
        ]);
        $migration = require database_path('migrations/2026_10_26_000001_add_webhook_authentication_settings.php');

        $migration->up();
        $migration->down();

        $this->assertFalse(Schema::hasColumn('webhooks', 'authentication_mode'));
        $this->assertFalse(Schema::hasColumn('webhooks', 'header_name'));
        $this->assertFalse(Schema::hasColumn('webhooks', 'header_secret'));
        $this->assertTrue(Schema::hasTable('webhook_deliveries'));
        $this->assertTrue(Schema::hasTable('unrelated_example'));
        $this->assertSame('whsec_example_existing', DB::table('webhooks')->find($id)->secret);
    }

    private function createLegacySchema(): void
    {
        config()->set('database.connections.webhook_migration', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::setDefaultConnection('webhook_migration');
        $migration = require database_path('migrations/2026_07_18_000006_create_webhooks_table.php');
        $migration->up();
    }
}
