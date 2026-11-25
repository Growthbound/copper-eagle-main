<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;

final class HomePlumbingReport
{
    public const FILENAME = 'home-plumbing-report.pdf';

    /** @param array<string, string> $answers */
    public function pdf(array $answers): string
    {
        $fonts = resource_path('fonts/outfit');
        $cache = storage_path('framework/cache');
        $images = public_path('images/brand');
        $options = (new Options)
            ->setChroot([$fonts, $images])
            ->setFontDir($cache)
            ->setFontCache($cache)
            ->setIsFontSubsettingEnabled(true)
            ->setIsRemoteEnabled(false)
            ->setIsJavascriptEnabled(false);

        $pdf = new Dompdf($options);
        $pdf->setPaper('letter');
        $pdf->addInfo('Title', 'Your home plumbing report');
        $pdf->addInfo('Author', (string) config('site.name'));
        $pdf->loadHtml(view('funnels.home-plumbing-report', [
            'checklist' => HomePlumbingQuiz::checklist($answers),
            'sources' => HomePlumbingQuiz::SOURCES,
            'palette' => Theme::palette(),
            'fonts' => $fonts,
            'logo' => $images.'/logo-horizontal.png',
        ])->render(), 'UTF-8');
        $pdf->render();

        return $pdf->output();
    }
}
