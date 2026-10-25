<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Support\SiteLogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These cover uploaded logos; the bundled brand files are covered in
        // EntityIdentityTest, so they are switched off here.
        config()->set('theme.logos', []);
        SiteLogo::flush();
    }

    protected function tearDown(): void
    {
        SiteLogo::flush();

        parent::tearDown();
    }

    /**
     * Structured data and the header render on every page, including before
     * migrations have run. A site that has not chosen a logo answers without a
     * query, and answers nothing rather than a placeholder mark.
     */
    public function test_a_site_without_a_configured_logo_resolves_to_nothing_without_touching_the_database(): void
    {
        config()->set('site.logo_media_id', null);
        SiteLogo::flush();

        DB::enableQueryLog();

        $logo = SiteLogo::resolve();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNull($logo);
        $this->assertSame([], $queries);
    }

    /**
     * Four surfaces resolve the logo on one page render — the header mark, the
     * footer mark, the business entity and the share card. Each would otherwise
     * cost a table check and a lookup.
     */
    public function test_an_uploaded_logo_is_looked_up_once_per_request(): void
    {
        $media = $this->uploadLogo('uploaded-logo.png', 512, 512);
        config()->set('site.logo_media_id', $media->id);
        SiteLogo::flush();

        DB::enableQueryLog();

        for ($i = 0; $i < 4; $i++) {
            $this->assertSame(512, SiteLogo::resolve()['width']);
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            2,
            count($queries),
            'The logo is being re-resolved for every surface that asks for it.',
        );
    }

    /**
     * The owner edits the name and the logo from the admin, and
     * `ApplySiteDetails` rewrites both per request. Caching the answer flatly
     * would serve one request's wordmark to the next one that changed it.
     */
    public function test_the_cached_answer_follows_a_setting_the_owner_changes(): void
    {
        $first = $this->uploadLogo('first-logo.png', 512, 512);
        $second = $this->uploadLogo('second-logo.png', 640, 640);

        config()->set('site.logo_media_id', $first->id);
        $this->assertSame($first->url(), SiteLogo::resolve()['url']);

        config()->set('site.logo_media_id', $second->id);
        $this->assertSame($second->url(), SiteLogo::resolve()['url'], 'The logo survived a change it should not have.');

        config()->set('site.logo_media_id', null);
        $this->assertNull(SiteLogo::resolve(), 'A removed logo is still being served.');
    }

    /**
     * A setting can outlive the image it names. The answer is then nothing,
     * not some other image from the library.
     */
    public function test_a_deleted_logo_resolves_to_nothing_rather_than_borrowing_one(): void
    {
        $this->uploadLogo('other-image.png', 512, 512);
        $logo = $this->uploadLogo('deleted-logo.png', 512, 512);
        config()->set('site.logo_media_id', $logo->id);
        $logo->delete();
        SiteLogo::flush();

        $this->assertNull(SiteLogo::resolve());
    }

    private function uploadLogo(string $name, int $width, int $height): Media
    {
        return Media::create([
            'path' => 'media/'.$name,
            'original_name' => $name,
            'mime_type' => 'image/png',
            'size' => 4096,
            'width' => $width,
            'height' => $height,
        ]);
    }
}
