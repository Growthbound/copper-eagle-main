<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The boundary the whole template rests on: config/site.php is business
 * identity, and anything that renders as page content is a record in content/.
 *
 * Two people maintain this site — an agent working in the repository and an
 * owner working in the admin — and the arrangement only stays comprehensible
 * while there is exactly one answer to who edits what. This test is what stops
 * page content drifting back into config the next time something is in a hurry.
 */
class ContentOwnershipTest extends TestCase
{
    public function test_config_holds_business_identity_and_nothing_else(): void
    {
        $config = require dirname(__DIR__, 2).'/config/site.php';

        $this->assertSame(
            ['name', 'logo_media_id', 'business_type', 'description', 'phone', 'email', 'address', 'social_links', 'navigation'],
            array_keys($config),
            'config/site.php gained a key. Page content belongs in content/, not here.',
        );
    }

    /**
     * A new install starts with no records, so only the directories are
     * required. Blocks reading an empty type render nothing rather than fail.
     */
    public function test_every_content_type_is_a_directory_of_records(): void
    {
        foreach (['services', 'areas', 'faqs', 'testimonials', 'benefits', 'process', 'resources'] as $type) {
            $this->assertDirectoryExists(
                dirname(__DIR__, 2).'/content/'.$type,
                $type.' has no record directory.',
            );
        }
    }

    public function test_every_record_directory_explains_itself(): void
    {
        foreach (['services', 'areas', 'faqs', 'testimonials'] as $type) {
            $this->assertFileExists(
                dirname(__DIR__, 2).'/content/'.$type.'/README.md',
                $type.' has no README, so nothing can discover how to write one.',
            );
        }
    }
}
