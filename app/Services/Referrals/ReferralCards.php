<?php

namespace App\Services\Referrals;

use App\Models\Referral;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class ReferralCards
{
    public function generate(int $quantity): string
    {
        if ($quantity < 1 || $quantity > 100) {
            throw new InvalidArgumentException('Generate between 1 and 100 cards at a time.');
        }
        $batch = (string) Str::uuid();
        DB::transaction(function () use ($quantity, $batch): void {
            for ($i = 0; $i < $quantity; $i++) {
                for ($attempt = 0; $attempt < 10; $attempt++) {
                    $code = bin2hex(random_bytes(6));
                    try {
                        // A nested transaction provides a savepoint on PostgreSQL after a collision.
                        DB::transaction(fn () => Referral::create(['code' => $code, 'batch_id' => $batch, 'referral_url' => Referral::urlForCode($code)]));

                        continue 2;
                    } catch (UniqueConstraintViolationException) {
                        // Generate a different code; never replace an existing card.
                    }
                }
                throw new RuntimeException('Unable to generate a unique card. Try again.');
            }
        });

        return $batch;
    }

    public function png(Referral $referral): string
    {
        return Builder::create()->writer(new PngWriter)->data($referral->publicUrl())
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)->size(600)->margin(40)
            ->labelText($referral->code)->labelFont(new NotoSans(strlen($referral->code) > 30 ? 14 : 28))
            ->build()->getString();
    }

    public function zip(string $batch): string
    {
        $referrals = Referral::where('batch_id', $batch)->orderBy('id')->get();
        abort_if($referrals->isEmpty(), 404);
        abort_if($referrals->count() > 100, 422);
        $path = tempnam(sys_get_temp_dir(), 'referral-cards-');
        if ($path === false) {
            throw new RuntimeException('Unable to prepare the card download.');
        }
        $zip = new ZipArchive;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to prepare the card archive.');
            }
            foreach ($referrals as $referral) {
                if (! $zip->addFromString($referral->code.'.png', $this->png($referral))) {
                    throw new RuntimeException('Unable to add a card to the archive.');
                }
            }
            $zip->close();

            return file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
