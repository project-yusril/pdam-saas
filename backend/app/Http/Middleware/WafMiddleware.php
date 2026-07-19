<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class WafMiddleware
{
    private array $blockedPatterns = [
        // SQL injection
        '/(\bUNION\b.*\bSELECT\b)/i',
        '/(\bDROP\b.*\bTABLE\b)/i',
        '/(\bINSERT\b.*\bINTO\b)/i',
        "/(\bSELECT\b.*\bFROM\b)/i",
        '/(\bDELETE\b.*\bFROM\b)/i',
        '/(\bUPDATE\b.*\bSET\b)/i',
        "/--[\s]*$/m",
        '/\/\*.*\*\//s',
        // XSS
        '/<script\b[^>]*>.*?<\/script>/is',
        '/javascript\s*:/i',
        '/on\w+\s*=/i',
        '/<iframe/i',
        // Path traversal
        '/\.\.\/\.\./',
        '/\/etc\/passwd/i',
        '/cmd\.exe/i',
    ];

    public function handle(Request $request, Closure $next)
    {
        $allInput = array_merge($request->query(), $request->input() ?? [], $request->headers->all());

        foreach ($allInput as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            foreach ($this->blockedPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    abort(403, 'Request diblokir oleh WAF: pattern detected in '.$key);
                }
            }
        }

        return $next($request);
    }
}
