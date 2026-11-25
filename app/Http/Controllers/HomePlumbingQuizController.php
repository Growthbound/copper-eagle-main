<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyOfLead;
use App\Jobs\SendHomePlumbingReport;
use App\Models\Lead;
use App\Support\Attribution;
use App\Support\HomePlumbingQuiz;
use App\Support\HomePlumbingReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HomePlumbingQuizController extends Controller
{
    private const CONTACT_FIELDS = [
        'first_name' => ['First name', 'text', 'given-name'],
        'last_name' => ['Last name', 'text', 'family-name'],
        'email' => ['Email', 'email', 'email'],
        'phone' => ['Phone number', 'tel', 'tel'],
    ];

    private const PRIVATE_HEADERS = [
        'Cache-Control' => 'private, no-store, max-age=0',
        'Referrer-Policy' => 'no-referrer',
        'X-Robots-Tag' => 'noindex, nofollow',
    ];

    public function show(Request $request, ?string $step = null): Response|RedirectResponse
    {
        $preview = $this->preview($request);
        $state = $this->state($request);
        $next = $this->nextStep($state);
        if ($step === 'report') {
            return $next === 'complete'
                ? response(app(HomePlumbingReport::class)->pdf($state['answers']), headers: [
                    ...self::PRIVATE_HEADERS,
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.HomePlumbingReport::FILENAME.'"',
                ])
                : $this->redirect($request, $next);
        }
        if ($step !== null && ($redirect = $this->guardStep($request, $step, $next))) {
            return $redirect;
        }

        $questions = HomePlumbingQuiz::QUESTIONS;
        $steps = [...array_keys($questions), 'contact', 'complete'];
        $position = $step === null ? null : array_search($step, $steps, true);

        return response()->view($preview ? 'template-previews.home-plumbing-quiz' : 'funnels.home-plumbing-quiz', [
            'step' => $step,
            'preview' => $preview,
            'routeName' => $this->routeName($request),
            'next' => $next,
            'position' => $position,
            'previous' => $position !== null && $position > 0 ? $steps[$position - 1] : null,
            'questions' => $questions,
            'answers' => $state['answers'] ?? [],
            'fields' => self::CONTACT_FIELDS,
            'checklist' => $step === 'complete' ? HomePlumbingQuiz::checklist($state['answers']) : [],
            'sources' => HomePlumbingQuiz::SOURCES,
            'inspection' => $state['inspection'] ?? null,
        ], headers: self::PRIVATE_HEADERS);
    }

    public function store(Request $request, ?string $step = null): RedirectResponse
    {
        $preview = $this->preview($request);
        $state = $this->state($request);
        $next = $this->nextStep($state);

        if ($step === null) {
            $state = ['id' => (string) Str::uuid(), 'answers' => []];
        } elseif ($redirect = $this->guardStep($request, $step, $next)) {
            return $redirect;
        } elseif ($step === 'contact') {
            $state['inspection'] = $this->submit($request, $state, $preview);
        } else {
            $options = HomePlumbingQuiz::QUESTIONS[$step]['options'];
            $state['answers'][$step] = $request->validate(['answer' => ['required', 'string', Rule::in(array_keys($options))]], [
                'answer.required' => 'Choose an answer to continue.',
                'answer.in' => 'Choose one of the answers shown here.',
            ])['answer'];
        }

        $request->session()->put($this->routeName($request), $state);
        $questions = array_keys(HomePlumbingQuiz::QUESTIONS);
        $following = in_array($step, $questions, true) ? $questions[array_search($step, $questions, true) + 1] ?? 'contact' : null;

        return $this->redirect($request, $following ?? $this->nextStep($state));
    }

    private function submit(Request $request, array $state, bool $preview): string
    {
        $details = $request->validate([
            'first_name' => ['required', 'string', 'max:120', 'not_regex:/[\r\n]/'],
            'last_name' => ['required', 'string', 'max:120', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:40', 'regex:/\A\+?[\d .()\-]+\z/', function (string $attribute, mixed $value, $fail): void {
                if (is_string($value) && ! preg_match('/\A\d{7,15}\z/', preg_replace('/\D/', '', $value))) {
                    $fail('Enter a phone number with 7 to 15 digits.');
                }
            }],
            'inspection' => ['sometimes', 'accepted'],
        ], ['phone.regex' => 'Enter a valid phone number.']);
        $requested = isset($details['inspection']);
        unset($details['inspection']);
        $choice = $requested ? 'yes' : 'no';

        if ($preview) {
            return $choice;
        }

        $existing = Lead::query()->where('quiz_submission_id', $state['id'])->first();
        if ($existing?->quiz_inspection_choice !== null) {
            return $existing->quiz_inspection_choice;
        }
        if ($existing === null && ! RateLimiter::attempt('home-plumbing-quiz:'.$request->ip(), 5, fn () => true, 3600)) {
            throw ValidationException::withMessages(['phone' => 'You’ve sent several quizzes already. Give us a call if you’d like help.']);
        }

        $lead = $existing ?? new Lead;
        $lead->forceFill([
            ...$details,
            'name' => $details['first_name'].' '.$details['last_name'],
            'quiz_submission_id' => $state['id'],
            'quiz_inspection_choice' => $choice,
            'quiz_answers' => $state['answers'],
            'status' => $requested ? 'new' : 'archived',
            'service' => $requested ? 'Free home plumbing inspection' : 'Home plumbing quiz',
            'message' => HomePlumbingQuiz::leadMessage($state['answers'], $requested ? 'requested' : 'declined'),
            ...Attribution::stored(),
        ])->saveQuietly();

        SendHomePlumbingReport::dispatch($lead->id)->afterResponse();
        if ($requested) {
            NotifyOfLead::dispatch($lead->id)->afterResponse();
        }

        return $choice;
    }

    private function nextStep(array $state): ?string
    {
        if (! isset($state['id'])) {
            return null;
        }
        foreach (array_keys(HomePlumbingQuiz::QUESTIONS) as $question) {
            if (! isset($state['answers'][$question])) {
                return $question;
            }
        }

        return isset($state['inspection']) ? 'complete' : 'contact';
    }

    private function guardStep(Request $request, string $step, ?string $next): ?RedirectResponse
    {
        $steps = [...array_keys(HomePlumbingQuiz::QUESTIONS), 'contact', 'complete'];
        if ($next === null || ($next === 'complete' && $step !== 'complete')
            || array_search($step, $steps, true) > array_search($next, $steps, true)) {
            return $this->redirect($request, $next);
        }

        return null;
    }

    private function preview(Request $request): bool
    {
        $preview = $request->routeIs('template-preview.home-plumbing-quiz.*');
        abort_if($preview && app()->environment('production'), 404);

        return $preview;
    }

    private function routeName(Request $request): string
    {
        return $request->routeIs('template-preview.home-plumbing-quiz.*') ? 'template-preview.home-plumbing-quiz' : 'home-plumbing-quiz';
    }

    private function state(Request $request): array
    {
        return $request->session()->get($this->routeName($request), []);
    }

    private function redirect(Request $request, ?string $step): RedirectResponse
    {
        return redirect()->route($this->routeName($request).'.show', $step === null ? [] : ['step' => $step], 303);
    }
}
