<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContentPreviewController;
use App\Http\Controllers\DiscoveryFilesController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\ReferralCardController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TemplatePreviewController;
use App\Livewire\Admin\AnnouncementEditor;
use App\Livewire\Admin\ApiKeys;
use App\Livewire\Admin\ContentEditor;
use App\Livewire\Admin\ContentHistory;
use App\Livewire\Admin\ContentIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\IntegrationsEditor;
use App\Livewire\Admin\Leads;
use App\Livewire\Admin\Login;
use App\Livewire\Admin\MediaLibrary;
use App\Livewire\Admin\NavigationFooterEditor;
use App\Livewire\Admin\Redirects;
use App\Livewire\Admin\Referrals;
use App\Livewire\Admin\SiteDetailsEditor;
use App\Livewire\Admin\ThemeEditor;
use App\Livewire\Admin\Webhooks;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', PageController::class)->name('home');

Route::get('/referrals/{code}', [ReferralController::class, 'show'])
    ->where('code', '[A-Za-z0-9_-]{1,64}')
    ->middleware('throttle:60,1,referral-visits:')
    ->name('referrals.show');

Route::get('/referrals/{code}/welcome', [ReferralController::class, 'welcome'])
    ->where('code', '[A-Za-z0-9_-]{1,64}')
    ->name('referrals.welcome');

Route::post('/referrals/{code}', [ReferralController::class, 'store'])
    ->where('code', '[A-Za-z0-9_-]{1,64}')
    ->middleware('throttle:5,1,referral-registration:')
    ->name('referrals.store');

// Keep malformed referral codes outside the managed-page fallback for every
// method; otherwise a rejected POST becomes a misleading 405 response.
Route::match(['GET', 'POST'], '/referrals/{invalid}', fn () => abort(404))
    ->where('invalid', '.*');

$discoveryMiddleware = [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
];

Route::get('/media/{media}', PublicMediaController::class)
    ->withoutMiddleware($discoveryMiddleware)
    ->middleware('cache.headers:public;max_age=86400')
    ->name('media.public');

Route::get('/robots.txt', [DiscoveryFilesController::class, 'robots'])
    ->withoutMiddleware($discoveryMiddleware)
    ->middleware('cache.headers:public;max_age=3600');

Route::get('/llms.txt', [DiscoveryFilesController::class, 'llms'])
    ->withoutMiddleware($discoveryMiddleware)
    ->middleware('cache.headers:public;max_age=3600');

Route::get('/sitemap.xml', [DiscoveryFilesController::class, 'sitemap'])
    ->withoutMiddleware($discoveryMiddleware)
    ->middleware('cache.headers:public;max_age=3600')
    ->name('sitemap');

Route::get('/content-preview/{token}', ContentPreviewController::class)
    ->where('token', '[a-f0-9]{64}')
    ->name('content.preview');

/*
 * There is no public admin registration route: the owner account is created from the
 * command line with `php artisan admin:account`.
 */
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('login', Login::class)->middleware('guest')->name('login');

    Route::post('logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->middleware('auth')->name('logout');

    Route::middleware('auth')->group(function (): void {
        Route::get('/', Dashboard::class)->name('dashboard');
        Route::get('leads', Leads::class)->name('leads');
        Route::get('referrals', Referrals::class)->name('referrals');
        Route::get('referrals/cards/{referral}', [ReferralCardController::class, 'show'])->name('referrals.card');
        Route::get('referrals/batches/{batch}', [ReferralCardController::class, 'batch'])->whereUuid('batch')->name('referrals.batch');
        Route::get('media', MediaLibrary::class)->name('media');
        Route::get('content', ContentIndex::class)->name('content.index');
        Route::get('content/create', ContentIndex::class)->name('content.create');
        Route::get('content/{entry}/history', ContentHistory::class)->name('content.history');
        Route::get('content/{entry}', ContentEditor::class)->name('content.edit');
        Route::get('redirects', Redirects::class)->name('redirects');
        Route::get('site-structure', NavigationFooterEditor::class)->name('site-structure');
        Route::get('keys', ApiKeys::class)->name('keys');
        Route::get('theme', ThemeEditor::class)->name('theme');
        Route::get('details', SiteDetailsEditor::class)->name('details');
        Route::get('tracking', IntegrationsEditor::class)->name('tracking');
        Route::get('announcement', AnnouncementEditor::class)->name('announcement');
        Route::get('webhooks', Webhooks::class)->name('webhooks');
    });
});

/*
 * Pages generated from the records in content/services and content/areas.
 *
 * Each of these checks content/pages first, so a page file at the same slug
 * replaces the generated page without needing a special case here.
 */
Route::get('services', [ServiceController::class, 'index'])->name('services.index');
Route::get('blog', BlogController::class)->name('blog.index');
Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
Route::get('services/{service}/{area}', [ServiceController::class, 'inArea'])->name('services.area');
Route::get('areas', [AreaController::class, 'index'])->name('areas.index');
Route::get('areas/{area}', [AreaController::class, 'show'])->name('areas.show');

Route::get('/templates', [TemplatePreviewController::class, 'index'])
    ->name('template-preview.index');

Route::get('/_templates/pricing', [TemplatePreviewController::class, 'pricing'])
    ->name('template-preview.pricing');

Route::get('/_templates/faq', [TemplatePreviewController::class, 'faq'])
    ->name('template-preview.faq');

Route::get('/_templates/footer', [TemplatePreviewController::class, 'footer'])
    ->name('template-preview.footer');

Route::get('/_templates/contact-form', [TemplatePreviewController::class, 'contactForm'])
    ->name('template-preview.contact-form');

Route::get('/_templates/image-split-cta', [TemplatePreviewController::class, 'imageSplitCta'])
    ->name('template-preview.image-split-cta');

Route::get('/_templates/process-steps', [TemplatePreviewController::class, 'processSteps'])
    ->name('template-preview.process-steps');

Route::get('/_templates/story-checklist', [TemplatePreviewController::class, 'storyChecklist'])
    ->name('template-preview.story-checklist');

Route::get('/_templates/team-grid', [TemplatePreviewController::class, 'teamGrid'])
    ->name('template-preview.team-grid');

Route::get('/_templates/testimonials', [TemplatePreviewController::class, 'testimonials'])
    ->name('template-preview.testimonials');

Route::get('/_templates/video-testimonial-carousel', [TemplatePreviewController::class, 'videoTestimonialCarousel'])
    ->name('template-preview.video-testimonial-carousel');

Route::get('/_templates/{template}', [TemplatePreviewController::class, 'show'])
    ->where('template', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('template-preview.show');

/*
 * Any URL not claimed by a route above is resolved against content/pages.
 *
 * A fallback rather than a catch-all pattern: it runs only when nothing else
 * matched, so routes added later — API endpoints, admin screens, media
 * delivery — keep working without anyone remembering to exclude them here.
 */
Route::fallback(PageController::class);
