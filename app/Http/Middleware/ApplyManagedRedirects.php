<?php

namespace App\Http\Middleware;

use App\Content\RedirectValidator;
use App\Models\RedirectRule;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyManagedRedirects
{
    public function __construct(private RedirectValidator $validator) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        if (! Schema::hasTable('redirect_rules')) {
            return $next($request);
        }

        $source = $this->validator->normalizePath($request->getPathInfo());

        if ($source === null) {
            return $next($request);
        }

        $rule = RedirectRule::query()
            ->where('source_path', $source)
            ->where('enabled', true)
            ->first();

        if ($rule === null) {
            return $next($request);
        }

        $target = $rule->target;

        if ($this->validator->normalizePath($target) !== null && $request->getQueryString() !== null) {
            $target .= '?'.$request->getQueryString();
        }

        return redirect()->to($target, $rule->status_code);
    }
}
