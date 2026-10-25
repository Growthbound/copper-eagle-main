<?php

namespace Tests\Feature;

use App\Content\ServiceRepository;
use App\Content\TestimonialRepository;
use App\Livewire\Admin\SiteDetailsEditor;
use App\Models\Setting;
use App\Models\User;
use App\Support\GoogleReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\IsolatesManagedContent;
use Tests\Concerns\UsesFixtureContent;
use Tests\TestCase;

class ReviewsTest extends TestCase
{
    use IsolatesManagedContent;
    use RefreshDatabase;
    use UsesFixtureContent;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->setUpFixtureContent();
        $this->fixturePage('/', [
            [
                'type' => 'hero/split',
                'props' => [
                    'heading' => 'Fixture homepage',
                    'body' => 'A homepage that exists only for this test.',
                ],
            ],
            ['type' => 'trust-bar/google-rating'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownFixtureContent();

        parent::tearDown();
    }

    /**
     * A quote is published only from a testimonial record the business holds
     * permission for. Without one, the section is absent rather than filled
     * with sample quotes.
     */
    public function test_no_testimonial_is_published_without_a_record_behind_it(): void
    {
        foreach (['/', $this->fixtureService(), $this->fixtureArea()] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('testimonial-section', false)
                ->assertDontSee('In their words');
        }
    }

    public function test_a_testimonial_scoped_to_a_service_only_shows_there(): void
    {
        $scoped = $this->fixtureService();
        $other = $this->fixtureService('fixture-other-service', ['name' => 'Fixture Other']);
        $this->writeRecordFixture('testimonials', 'fixture-scoped', [
            'name' => 'Fixture Reviewer',
            'location' => 'Fixture Area',
            'quote' => 'Scoped to exactly one service and no other.',
            'rating' => 5,
            'services' => ['fixture-service'],
        ]);

        $this->get($scoped)->assertOk()->assertSee('Fixture Reviewer');
        $this->get($other)->assertOk()->assertDontSee('Fixture Reviewer');
    }

    public function test_unscoped_testimonials_show_on_every_service(): void
    {
        $this->writeRecordFixture('testimonials', 'fixture-unscoped', [
            'name' => 'Fixture Reviewer',
            'quote' => 'This approved fixture appears on every service.',
            'rating' => 5,
        ]);

        $this->get($this->fixtureService())->assertOk()->assertSee('Fixture Reviewer');
    }

    public function test_the_scoped_repository_narrows_correctly(): void
    {
        $this->fixtureService();
        $this->fixtureService('fixture-other-service', ['name' => 'Fixture Other']);
        $this->writeRecordFixture('testimonials', 'fixture-scoped', [
            'name' => 'Jordan Example',
            'quote' => 'Scoped to one service.',
            'services' => ['fixture-service'],
        ]);
        $this->writeRecordFixture('testimonials', 'fixture-unscoped', [
            'name' => 'Taylor Example',
            'quote' => 'Shown everywhere.',
        ]);

        $repository = app(TestimonialRepository::class);
        $services = app(ServiceRepository::class);
        $authors = fn ($testimonials): array => $testimonials->map->author()->sort()->values()->all();

        $this->assertSame(['Taylor Example'], $authors($repository->for()));
        $this->assertSame(
            ['Jordan Example', 'Taylor Example'],
            $authors($repository->for($services->find('fixture-service'))),
        );
        $this->assertSame(['Taylor Example'], $authors($repository->for($services->find('fixture-other-service'))));
    }

    public function test_nothing_google_related_renders_before_it_is_configured(): void
    {
        $this->assertFalse(GoogleReviews::isConfigured());
        $this->assertNull(GoogleReviews::current());

        $this->get('/')->assertOk()->assertDontSee('class="google-rating"', false);
    }

    public function test_the_rating_is_fetched_and_displayed(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response([
            'rating' => 4.8,
            'userRatingCount' => 127,
            'googleMapsUri' => 'https://maps.google.com/?cid=123',
        ])]);

        GoogleReviews::save('ChIJexample', 'test-key');

        $this->assertTrue(GoogleReviews::refresh()['ok']);

        $this->get('/')
            ->assertOk()
            ->assertSee('4.8')
            ->assertSee('127 Google reviews')
            ->assertSee('https://maps.google.com/?cid=123', false);
    }

    /**
     * The count shown must be every review, not a filtered subset: presenting a
     * selection of a public review feed is what the FTC rule prohibits.
     */
    public function test_the_full_review_count_is_shown_not_a_filtered_one(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response([
            'rating' => 3.9,
            'userRatingCount' => 64,
            'googleMapsUri' => 'https://maps.google.com/?cid=123',
        ])]);

        GoogleReviews::save('ChIJexample', 'test-key');
        GoogleReviews::refresh();

        // A middling rating is displayed exactly as Google reports it.
        $this->get('/')->assertOk()->assertSee('3.9')->assertSee('64 Google reviews');
    }

    public function test_a_google_outage_leaves_the_previous_rating_in_place(): void
    {
        // A second Http::fake would not replace the first stub, so the outage
        // is toggled from inside the handler instead.
        $googleIsDown = false;

        Http::fake(function () use (&$googleIsDown) {
            return $googleIsDown
                ? Http::response('', 503)
                : Http::response(['rating' => 4.8, 'userRatingCount' => 127]);
        });

        GoogleReviews::save('ChIJexample', 'test-key');
        GoogleReviews::refresh();

        $googleIsDown = true;

        $result = GoogleReviews::refresh();

        $this->assertFalse($result['ok']);
        $this->assertSame(4.8, GoogleReviews::current()['rating']);

        $this->get('/')->assertOk()->assertSee('4.8');
    }

    public function test_a_rejected_key_reports_what_to_check(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response('', 403)]);

        GoogleReviews::save('ChIJexample', 'wrong-key');

        $this->assertStringContainsString('Check the Place ID', GoogleReviews::refresh()['message']);
    }

    public function test_a_place_with_no_ratings_is_reported_rather_than_shown_as_zero(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response(['displayName' => ['text' => 'New Business']])]);

        GoogleReviews::save('ChIJnew', 'test-key');

        $this->assertStringContainsString('no rating', GoogleReviews::refresh()['message']);
        $this->assertNull(GoogleReviews::current());
    }

    public function test_the_owner_configures_it_from_the_admin(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response([
            'rating' => 4.9,
            'userRatingCount' => 88,
        ])]);

        $this->actingAs(User::factory()->create());

        Livewire::test(SiteDetailsEditor::class)
            ->set('placeId', 'ChIJexample')
            ->set('placesApiKey', 'test-key')
            ->call('saveGoogle')
            ->assertHasNoErrors();

        $this->assertSame(4.9, GoogleReviews::current()['rating']);
    }

    public function test_clearing_the_place_id_removes_the_rating(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response(['rating' => 4.8, 'userRatingCount' => 127])]);

        GoogleReviews::save('ChIJexample', 'test-key');
        GoogleReviews::refresh();

        $this->actingAs(User::factory()->create());

        Livewire::test(SiteDetailsEditor::class)
            ->set('placeId', '')
            ->set('placesApiKey', '')
            ->call('saveGoogle');

        $this->assertNull(GoogleReviews::current());
        $this->get('/')->assertOk()->assertDontSee('class="google-rating"', false);
    }

    public function test_a_stored_rating_is_treated_as_stale_after_a_day(): void
    {
        Setting::write([
            'google.rating' => '4.8',
            'google.rating_count' => '127',
            'google.fetched_at' => now()->subHours(30)->toIso8601String(),
        ]);

        $this->assertTrue(GoogleReviews::isStale());

        Setting::write(['google.fetched_at' => now()->subHour()->toIso8601String()]);

        $this->assertFalse(GoogleReviews::isStale());
    }
}
