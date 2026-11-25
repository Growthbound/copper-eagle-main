@php
    $question = $questions[$step] ?? null;
    $questionCount = count($questions);
    $heading = match ($step) {
        'contact' => 'Where can we send your report?',
        'complete' => $preview ? 'Preview complete' : 'Your plumbing report is ready',
        default => $question['title'] ?? 'Home plumbing quiz',
    };
    $logo = App\Support\SiteLogo::resolve();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $heading }} | {{ config('site.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($themeOverrides = App\Support\Theme::cssOverrides())
        <style>{{ $themeOverrides }}</style>
    @endif
    @if (! $preview && ($step === null || $question !== null))
        <x-tracking.clarity />
    @endif
</head>
<body class="plumbing-quiz">
    <header class="plumbing-quiz-masthead">
        <a href="{{ $preview ? route('template-preview.index') : route('home') }}">
            @if ($logo)
                <img src="{{ $logo['url'] }}" alt="{{ config('site.name') }}" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}">
            @else
                {{ config('site.name') }}
            @endif
        </a>
    </header>

    <main class="plumbing-quiz-shell" id="main-content">
        @if ($preview)
            <p class="plumbing-quiz-preview">Template preview. Use sample details.</p>
        @endif

        @if ($step === null)
            <section class="plumbing-quiz-hero" aria-labelledby="quiz-heading">
                <div class="plumbing-quiz-hero-copy">
                    <p class="plumbing-quiz-label">Plan for the next 3–5 years</p>
                    <h1 id="quiz-heading">{{ $heading }}</h1>
                    <p>Wondering what your home’s plumbing might need next? We’ll help you figure out what to watch for and what you can check today.</p>
                </div>
                <img src="{{ asset('images/brand/photos/copper-pipe.jpg') }}" width="1200" height="800" alt="">
            </section>
            <section class="plumbing-quiz-start" aria-labelledby="quiz-start-heading">
                <h2 id="quiz-start-heading">Get your plumbing checklist</h2>
                <p>Answer {{ $questionCount }} quick questions about your water heater, drains, and fixtures. We’ll email you a report based on your answers.</p>
                @if ($next !== null)
                    <a class="button button-orange" href="{{ route($routeName.'.show', ['step' => $next]) }}">{{ $next === 'complete' ? 'View my report' : 'Continue my quiz' }}</a>
                @else
                    <form method="post" action="{{ route($routeName.'.store') }}">
                        @csrf
                        <button class="button button-orange" type="submit">Start the quiz</button>
                    </form>
                @endif
            </section>
        @else
            @if ($question || $step === 'contact')
                <div class="plumbing-quiz-progress">
                    <div class="plumbing-quiz-progress-label">
                        <span class="plumbing-quiz-label">{{ $question['topic'] ?? 'Your details' }}</span>
                        <span>Step {{ $position + 1 }} of {{ $questionCount + 1 }}</span>
                    </div>
                    <progress aria-label="Quiz progress" value="{{ $position + 1 }}" max="{{ $questionCount + 1 }}"></progress>
                </div>
            @endif

            <section class="plumbing-quiz-content" aria-labelledby="quiz-heading">
                @if ($step === 'complete')
                    <p class="plumbing-quiz-label">Your next 3–5 years</p>
                @endif
                <h1 id="quiz-heading">{{ $heading }}</h1>

                @if ($question)
                    <p class="plumbing-quiz-hint" id="quiz-hint">{{ $question['hint'] }}</p>
                    <form method="post" action="{{ route($routeName.'.store', ['step' => $step]) }}">
                        @csrf
                        <fieldset class="plumbing-quiz-options" aria-labelledby="quiz-heading" aria-describedby="quiz-hint @error('answer') quiz-answer-error @enderror" data-clarity-mask="true">
                            @foreach ($question['options'] as $value => $label)
                                <label class="plumbing-quiz-choice">
                                    <input type="radio" required name="answer" value="{{ $value }}" @checked(old('answer', $answers[$step] ?? '') === $value)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                        @error('answer') <p class="quote-error" id="quiz-answer-error" role="alert">{{ $message }}</p> @enderror
                        <button class="button button-orange" type="submit">Continue</button>
                    </form>
                @elseif ($step === 'contact')
                    <p class="plumbing-quiz-hint">We’ll email you a PDF with what to check now and what to plan for over the next 3–5 years.</p>
                    <form method="post" action="{{ route($routeName.'.store', ['step' => $step]) }}">
                        @csrf
                        <div class="plumbing-quiz-fields">
                            @foreach ($fields as $name => [$label, $type, $autocomplete])
                                <div class="plumbing-quiz-field">
                                    <label class="quote-label" for="quiz-{{ $name }}">{{ $label }}</label>
                                    <input class="quote-input" id="quiz-{{ $name }}" name="{{ $name }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" value="{{ old($name) }}" required @error($name) aria-invalid="true" aria-describedby="quiz-{{ $name }}-error" @enderror>
                                    @error($name) <p class="quote-error" id="quiz-{{ $name }}-error" role="alert">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>
                        <section class="plumbing-quiz-inspection" aria-labelledby="quiz-inspection-heading">
                            <h2 id="quiz-inspection-heading">Want a free home plumbing inspection?</h2>
                            <label class="plumbing-quiz-choice">
                                <input type="checkbox" name="inspection" value="1" @checked(old('inspection') === '1') aria-describedby="quiz-inspection-note">
                                <span>Yes, I’d like a free inspection</span>
                            </label>
                            <p id="quiz-inspection-note">If you check the box, we’ll call to find a time that works for you.</p>
                            @error('inspection') <p class="quote-error" role="alert">{{ $message }}</p> @enderror
                        </section>
                        <button class="button button-orange" type="submit">Email my report</button>
                        <a class="plumbing-quiz-privacy" href="{{ url('/privacy-policy/') }}">Privacy Policy</a>
                    </form>
                @else
                    @if ($preview)
                        <p class="plumbing-quiz-hint">Download a sample of the report. This preview sends no emails.</p>
                    @else
                        <p class="plumbing-quiz-hint">Look for an email with your PDF. You can also save a copy here.</p>
                    @endif
                    <a class="button button-orange" href="{{ route($routeName.'.show', ['step' => 'report']) }}">Download my PDF</a>
                    @if ($inspection === 'yes' && ! $preview)
                        <p class="plumbing-quiz-inspection">We received your request for a free home plumbing inspection. We’ll call to find a time.</p>
                    @endif
                    <ul class="plumbing-quiz-results" aria-label="Plumbing recommendations">
                        @foreach ($checklist as $item)
                            <li>
                                <p class="plumbing-quiz-label">{{ $item['priority'] }}</p>
                                <h2>{{ $item['title'] }}</h2>
                                <p>{{ $item['reason'] }}</p>
                                <p><strong>What to check.</strong> {{ $item['check'] }}</p>
                            </li>
                        @endforeach
                    </ul>

                    <form method="post" action="{{ route($routeName.'.store') }}">
                        @csrf
                        <button class="plumbing-quiz-back" type="submit">Start a new quiz</button>
                    </form>

                    <details class="plumbing-quiz-sources">
                        <summary>Sources for your checklist</summary>
                        <ul>
                            @foreach ($sources as $label => $href)
                                <li><a href="{{ $href }}">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                @if ($step !== 'complete')
                    <a class="plumbing-quiz-back" href="{{ route($routeName.'.show', ['step' => $previous]) }}">Back</a>
                @endif
            </section>
        @endif
    </main>
</body>
</html>
