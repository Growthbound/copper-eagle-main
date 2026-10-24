<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Referrals;
use App\Models\Referral;
use App\Models\User;
use App\Services\Referrals\ReferralCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;
use Zxing\QrReader;

class ReferralsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_and_downloads_require_login(): void
    {
        $this->get('/admin/referrals')->assertRedirect('/admin/login');
        $this->get('/admin/referrals/cards/1')->assertRedirect('/admin/login');
        $this->get('/admin/referrals/batches/11111111-1111-4111-8111-111111111111')->assertRedirect('/admin/login');
    }

    public function test_batch_generation_persists_unique_unclaimed_cards_and_limits_quantity(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(Referrals::class)->set('quantity', 5)->call('generate')->assertHasNoErrors();
        $this->assertDatabaseCount('referrals', 5);
        $this->assertSame(5, Referral::distinct()->count('code'));
        $this->assertSame(1, Referral::distinct()->count('batch_id'));
        $this->assertSame(0, Referral::whereNotNull('registered_at')->count());
        Livewire::test(Referrals::class)->set('quantity', 101)->call('generate')->assertHasErrors('quantity');
        $this->assertDatabaseCount('referrals', 5);
    }

    public function test_admin_can_search_affiliates_and_filter_unclaimed_inventory(): void
    {
        $this->actingAs(User::factory()->create());
        Referral::create(['code' => 'taylor', 'name' => 'Taylor Example', 'business_name' => 'Example Co.',
            'phone' => '+15555550101', 'email' => 'taylor@example.test',
            'service_areas' => ['North Springfield', 'South Springfield'], 'registered_at' => now()]);
        Referral::create(['code' => 'unclaimed-test-card']);
        Livewire::test(Referrals::class)->assertSee('<option value="registered">Registered affiliates</option>', false);
        Livewire::test(Referrals::class)->set('status', 'unused')->assertSee('unclaimed-test-card')->assertDontSee('Taylor Example');
        Livewire::test(Referrals::class)->set('search', 'Example Co')->assertSee('Taylor Example')
            ->assertSee('+15555550101')->assertSee('taylor@example.test')
            ->assertSee('North Springfield, South Springfield')->assertDontSee('unclaimed-test-card');
    }

    #[DataProvider('qrCodes')]
    public function test_downloaded_qr_decodes_to_the_canonical_url_and_has_a_readable_code_label(string $code, string $origin): void
    {
        config(['app.url' => $origin]);
        $this->actingAs(User::factory()->create());
        $referral = Referral::create(['code' => $code]);
        $response = $this->withHeader('Host', 'attacker.test')->get('/admin/referrals/cards/'.$referral->id)->assertOk()->assertHeader('Content-Type', 'image/png');
        $bytes = $response->getContent();
        $this->assertSame($origin.'/referrals/'.$referral->code, $this->decodeQr($bytes));
        $size = getimagesizefromstring($bytes);
        $this->assertGreaterThan($size[0], $size[1]); // Extra caption space below the square QR.
        $this->assertStringContainsString($referral->code, $response->headers->get('Content-Disposition'));
    }

    public function test_zip_contains_every_code_in_the_selected_batch_only(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = app(ReferralCards::class)->generate(2);
        app(ReferralCards::class)->generate(1);
        $bytes = $this->get('/admin/referrals/batches/'.$batch)->assertOk()->getContent();
        $path = tempnam(sys_get_temp_dir(), 'qr-test-');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            $this->assertSame(2, $zip->numFiles);
            foreach (Referral::where('batch_id', $batch)->get() as $referral) {
                $png = $zip->getFromName($referral->code.'.png');
                $this->assertSame($referral->publicUrl(), $this->decodeQr($png));
            }
            $zip->close();
        } finally {
            unlink($path);
        }
    }

    public static function qrCodes(): array
    {
        $cases = [];
        foreach (['http://localhost', 'https://www.example.com'] as $origin) {
            foreach (['c2d809da3a90', 'a6c74ccd237f', '1b8c32b16626', 'c371cae4357a', '309ef7aad481'] as $code) {
                $cases[] = [$code, $origin];
            }
        }

        return $cases;
    }

    public function test_decoder_rejects_an_image_without_a_qr_in_every_orientation(): void
    {
        $image = imagecreatetruecolor(680, 722);
        imagefill($image, 0, 0, 0xFFFFFF);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $this->assertFalse($this->decodeQr($bytes));
    }

    private function decodeQr(string $png): string|false
    {
        $image = imagecreatefromstring($png);
        try {
            // This decoder can misidentify alignment patterns in one orientation.
            // Decode the actual exported pixels in each orientation, never a regenerated QR.
            foreach ([0, 90, 180, 270] as $angle) {
                $rotated = imagerotate($image, $angle, 0xFFFFFF);
                try {
                    $decoded = (new QrReader($rotated, QrReader::SOURCE_TYPE_RESOURCE, false))->text();
                    if (is_string($decoded)) {
                        return $decoded;
                    }
                } finally {
                    imagedestroy($rotated);
                }
            }

            return false;
        } finally {
            imagedestroy($image);
        }
    }
}
