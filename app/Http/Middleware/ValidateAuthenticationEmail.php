<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class ValidateAuthenticationEmail
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $field = null): Response
    {
        $request->validate($this->rules($field ?? Fortify::username()));

        return $next($request);
    }

    public function hasValidShape(Request $request, string $field): bool
    {
        return Validator::make($request->only($field), $this->rules($field))->passes();
    }

    /** @return array<string, list<string>> */
    private function rules(string $field): array
    {
        return [$field => ['bail', 'required', 'string', 'max:255']];
    }
}
