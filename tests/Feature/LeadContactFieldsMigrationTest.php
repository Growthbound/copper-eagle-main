<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadContactFieldsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_10_25_000001_add_first_last_name_and_text_choices_to_leads_table.php';

    private const ADDED = ['first_name', 'last_name', 'consent_service', 'consent_marketing'];

    public function test_a_fresh_database_has_the_new_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('leads', [...self::ADDED, 'name', 'consent_given', 'consent_text']));
    }

    public function test_an_existing_leads_table_keeps_its_rows_and_down_removes_only_the_new_columns(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION);

        $migration->down();

        foreach (self::ADDED as $column) {
            $this->assertFalse(Schema::hasColumn('leads', $column), "{$column} was not removed.");
        }
        $this->assertTrue(Schema::hasColumns('leads', ['name', 'email', 'phone', 'consent_given', 'consent_text', 'utm_source']));

        // A lead saved before this release, on the schema it was saved with.
        $id = DB::table('leads')->insertGetId([
            'name' => 'Taylor Example',
            'phone' => '555 555 0100',
            'status' => 'new',
            'consent_given' => true,
            'consent_text' => 'Earlier wording.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $row = DB::table('leads')->find($id);

        $this->assertSame('Taylor Example', $row->name);
        $this->assertSame('Earlier wording.', $row->consent_text);
        // Unknown rather than guessed: the earlier form never asked.
        $this->assertNull($row->first_name);
        $this->assertNull($row->last_name);
        $this->assertNull($row->consent_service);
        $this->assertNull($row->consent_marketing);
    }
}
