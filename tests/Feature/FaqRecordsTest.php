<?php

namespace Tests\Feature;

use App\Content\AreaRepository;
use App\Content\FaqRepository;
use App\Content\ServiceRepository;
use Illuminate\Support\Facades\File;
use Tests\Concerns\ReadsStructuredData;
use Tests\Concerns\WritesRecordFixtures;
use Tests\TestCase;

/**
 * FAQ records and the pages that show them.
 *
 * Every question here is a fixture, and the general questions are shown on a
 * fixture page with an FAQ block, so the subject stays the record-to-markup
 * mechanism rather than whichever questions a given site happens to publish.
 */
class FaqRecordsTest extends TestCase
{
    use ReadsStructuredData;
    use WritesRecordFixtures;

    private const QUESTION_PAGE = 'fixture-questions';

    private const FIRST_QUESTION = 'What happens after I book?';

    private const SECOND_QUESTION = 'Are estimates free?';

    private const UNORDERED_QUESTION = 'Can I reschedule a visit?';

    /** @var array<int, string> */
    private array $temporary = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Ordered deliberately against the alphabet: "Are…" would sort first
        // by name, so only the order field can put "What…" ahead of it.
        $this->writeFaq('fixture-general-first', [
            'question' => self::FIRST_QUESTION,
            'answer' => 'We confirm the time and send a reminder the day before.',
            'order' => 1,
        ]);
        $this->writeFaq('fixture-general-second', [
            'question' => self::SECOND_QUESTION,
            'answer' => 'Yes, every estimate is free and comes with no obligation.',
            'order' => 2,
        ]);
        $this->writeFaq('fixture-general-unordered', [
            'question' => self::UNORDERED_QUESTION,
            'answer' => 'Yes, with a day of notice.',
        ]);

        $this->writePage(self::QUESTION_PAGE, [
            'title' => 'Fixture questions',
            'blocks' => [['type' => 'faq/accordion', 'props' => ['heading' => 'Common questions']]],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            File::delete($path);
        }

        $this->temporary = [];
        $this->tearDownRecordFixtures();

        parent::tearDown();
    }

    public function test_general_questions_appear_on_a_page_with_an_faq_block(): void
    {
        $this->get('/'.self::QUESTION_PAGE)
            ->assertOk()
            ->assertSee(self::FIRST_QUESTION)
            ->assertSee('Common questions');
    }

    public function test_a_question_scoped_to_a_service_only_shows_there(): void
    {
        $scoped = $this->fixtureService();
        $other = $this->fixtureService('fixture-other-service', ['name' => 'Fixture Other']);
        $this->writeFaq('fixture-scoped', [
            'question' => 'Is this question scoped to one service?',
            'answer' => 'Yes.',
            'services' => ['fixture-service'],
        ]);

        $this->get($scoped)
            ->assertOk()
            ->assertSee('Is this question scoped to one service?');

        $this->get($other)
            ->assertOk()
            ->assertDontSee('Is this question scoped to one service?');
    }

    public function test_general_questions_still_show_on_a_service_page(): void
    {
        $this->get($this->fixtureService())
            ->assertOk()
            ->assertSee(self::SECOND_QUESTION);
    }

    public function test_a_scoped_question_is_kept_off_a_general_page(): void
    {
        $this->fixtureService();
        $this->writeFaq('fixture-scoped', [
            'question' => 'Is this question scoped to one service?',
            'answer' => 'Yes.',
            'services' => ['fixture-service'],
        ]);

        $this->get('/'.self::QUESTION_PAGE)
            ->assertOk()
            ->assertSee(self::FIRST_QUESTION)
            ->assertDontSee('Is this question scoped to one service?');
    }

    public function test_questions_can_be_scoped_to_an_area(): void
    {
        $scoped = $this->fixtureArea();
        $other = $this->fixtureArea('fixture-other-area', ['name' => 'Fixture Other Area']);
        $this->writeFaq('fixture-area-only', [
            'question' => 'Do you charge extra for travel here?',
            'answer' => 'No.',
            'areas' => ['fixture-area'],
        ]);

        $this->get($scoped)->assertOk()->assertSee('Do you charge extra for travel here?');
        $this->get($other)->assertOk()->assertDontSee('Do you charge extra for travel here?');
    }

    public function test_the_answers_are_in_the_markup_rather_than_loaded_later(): void
    {
        // Collapsed with <details>, so the text is present for search engines
        // and readable without JavaScript.
        $content = $this->get('/'.self::QUESTION_PAGE)->assertOk()->getContent();

        $this->assertStringContainsString('<details class="faq-item">', $content);
        $this->assertStringContainsString('send a reminder the day before', $content);
    }

    public function test_the_markup_describes_exactly_the_questions_shown(): void
    {
        $service = $this->fixtureService();
        $this->writeFaq('fixture-markup', [
            'question' => 'Does the markup list this question?',
            'answer' => 'Yes.',
            'services' => ['fixture-service'],
        ]);

        $faq = $this->faqNodeFrom($service);

        $asked = array_column($faq['mainEntity'], 'name');

        $this->assertContains('Does the markup list this question?', $asked);
        $this->assertContains(self::SECOND_QUESTION, $asked);

        $response = $this->get($service);

        foreach ($asked as $question) {
            $response->assertSee($question);
        }
    }

    public function test_a_page_without_questions_emits_no_faq_markup(): void
    {
        $this->writePage('fixture-quiet', [
            'title' => 'Quiet page',
            'blocks' => [['type' => 'cta/panel', 'props' => ['heading' => 'Nothing to ask']]],
        ]);

        $content = $this->get('/fixture-quiet')->assertOk()->getContent();

        $this->assertStringNotContainsString('FAQPage', $content);
    }

    public function test_answers_carry_into_the_structured_data(): void
    {
        $faq = $this->faqNodeFrom('/'.self::QUESTION_PAGE);

        $first = $faq['mainEntity'][0];

        $this->assertSame('Question', $first['@type']);
        $this->assertSame(self::FIRST_QUESTION, $first['name']);
        $this->assertSame('Answer', $first['acceptedAnswer']['@type']);
        $this->assertNotEmpty($first['acceptedAnswer']['text']);
    }

    public function test_a_record_without_a_question_is_rejected_by_name(): void
    {
        $this->writeFaq('broken', ['answer' => 'An answer to nothing.']);

        $this->artisan('content:check')
            ->expectsOutputToContain('needs a "question"')
            ->assertFailed();
    }

    public function test_ordering_is_deliberate(): void
    {
        $questions = app(FaqRepository::class)->for()->map(fn ($faq): string => $faq->question())->values()->all();

        $first = array_search(self::FIRST_QUESTION, $questions, true);
        $second = array_search(self::SECOND_QUESTION, $questions, true);
        $unordered = array_search(self::UNORDERED_QUESTION, $questions, true);

        $this->assertIsInt($first);
        $this->assertIsInt($second);
        $this->assertIsInt($unordered);
        $this->assertLessThan($second, $first, 'An explicit order beats the alphabet.');
        $this->assertLessThan($unordered, $second, 'Records without an order fall to the end.');
    }

    public function test_scoping_matches_the_records_it_names(): void
    {
        $this->fixtureService();
        $this->fixtureArea('fixture-unscoped-area', ['name' => 'Fixture Unscoped Area']);
        $this->writeFaq('fixture-scoped', [
            'question' => 'Is this question scoped to one service?',
            'answer' => 'Yes.',
            'services' => ['fixture-service'],
        ]);

        $repository = app(FaqRepository::class);
        $service = app(ServiceRepository::class)->find('fixture-service');
        $area = app(AreaRepository::class)->find('fixture-unscoped-area');

        $this->assertNotNull($service);
        $this->assertNotNull($area);
        $this->assertGreaterThan($repository->for()->count(), $repository->for($service)->count());
        $this->assertSame($repository->for()->count(), $repository->for(null, $area)->count());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeFaq(string $slug, array $data): void
    {
        $path = base_path('content/faqs/'.$slug.'.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->temporary[] = $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writePage(string $slug, array $data): void
    {
        $path = base_path('content/pages/'.$slug.'.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->temporary[] = $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function faqNodeFrom(string $path): array
    {
        $content = (string) $this->get($path)->assertOk()->getContent();

        foreach ($this->structuredDataIn($content) as $node) {
            if (($node['@type'] ?? null) === 'FAQPage') {
                return $node;
            }
        }

        $this->fail('No FAQPage node on '.$path);
    }
}
