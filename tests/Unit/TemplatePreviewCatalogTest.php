<?php

namespace Tests\Unit;

use App\Support\TemplatePreviewCatalog;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TemplatePreviewCatalogTest extends TestCase
{
    private string $previewPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previewPath = sys_get_temp_dir().'/template-catalog-'.bin2hex(random_bytes(6));
        File::makeDirectory($this->previewPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->previewPath);

        parent::tearDown();
    }

    public function test_it_discovers_sorted_valid_preview_files_and_excludes_the_index(): void
    {
        foreach ([
            'faq.blade.php',
            'contact-form.blade.php',
            'index.blade.php',
            'Bad_Name.blade.php',
            'notes.txt',
        ] as $file) {
            File::put($this->previewPath.'/'.$file, '');
        }

        $catalog = new TemplatePreviewCatalog($this->previewPath);

        $this->assertSame([
            [
                'slug' => 'contact-form',
                'title' => 'Contact Form',
                'href' => url('/_templates/contact-form'),
            ],
            [
                'slug' => 'faq',
                'title' => 'Faq',
                'href' => url('/_templates/faq'),
            ],
        ], $catalog->all());
    }

    public function test_a_missing_preview_directory_returns_an_empty_catalog(): void
    {
        $catalog = new TemplatePreviewCatalog($this->previewPath.'/missing');

        $this->assertSame([], $catalog->all());
    }

    public function test_it_only_finds_previews_that_exist_in_the_allow_listed_catalog(): void
    {
        File::put($this->previewPath.'/faq.blade.php', '');
        File::put($this->previewPath.'/Bad_Name.blade.php', '');

        $catalog = new TemplatePreviewCatalog($this->previewPath);

        $this->assertSame([
            'slug' => 'faq',
            'title' => 'Faq',
            'href' => url('/_templates/faq'),
        ], $catalog->find('faq'));
        $this->assertNull($catalog->find('missing'));
        $this->assertNull($catalog->find('Bad_Name'));
        $this->assertNull($catalog->find('../faq'));
    }
}
