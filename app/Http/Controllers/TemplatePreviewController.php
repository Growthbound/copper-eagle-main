<?php

namespace App\Http\Controllers;

use App\Support\TemplatePreviewCatalog;
use Illuminate\Contracts\View\View;

class TemplatePreviewController extends Controller
{
    public function contactForm(): View
    {
        $this->guardPreviewEnvironment();

        return view('template-previews.contact-form');
    }

    public function faq(): View
    {
        $this->guardPreviewEnvironment();

        return view('template-previews.faq');
    }

    public function footer(): View
    {
        $this->guardPreviewEnvironment();

        return view('template-previews.footer');
    }

    public function imageSplitCta(): View
    {
        $this->guardPreviewEnvironment();

        return view('template-previews.image-split-cta');
    }

    public function index(TemplatePreviewCatalog $catalog): View
    {
        $this->guardPreviewEnvironment();

        return view('template-previews.index', [
            'templates' => $catalog->all(),
            'environment' => app()->environment(),
        ]);
    }

    public function processSteps(): View
    {
        $this->guardPreviewEnvironment();

        return view('template-previews.process-steps');
    }

    public function pricing(): View
    {
        $this->guardPreviewEnvironment();

        return view('template-previews.pricing');
    }

    public function storyChecklist(): View
    {
        $this->guardPreviewEnvironment();

        return view('template-previews.story-checklist');
    }

    public function teamGrid(): View
    {
        $this->guardPreviewEnvironment();

        return view('template-previews.team-grid');
    }

    public function testimonials(): View
    {
        $this->guardPreviewEnvironment();

        return view('template-previews.testimonials');
    }

    public function videoTestimonialCarousel(): View
    {
        $this->guardPreviewEnvironment();

        return view('template-previews.video-testimonial-carousel');
    }

    public function show(string $template, TemplatePreviewCatalog $catalog): View
    {
        $this->guardPreviewEnvironment();

        $preview = $catalog->find($template);

        abort_if($preview === null, 404);

        return view('template-previews.'.$preview['slug']);
    }

    private function guardPreviewEnvironment(): void
    {
        abort_if(app()->environment('production'), 404);
    }
}
