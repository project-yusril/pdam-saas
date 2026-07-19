<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InputSanitizer
{
    public function handle(Request $request, Closure $next)
    {
        // Ambil input ke variabel lokal dulu; melempar hasil method (return by
        // value) langsung ke parameter by-reference memicu error PHP
        // "Only variables should be passed by reference".
        $query = $request->query();
        $this->sanitize($query);
        $request->query->replace($query);

        $input = $request->request->all();
        $this->sanitize($input);
        $request->request->replace($input);

        return $next($request);
    }

    private function sanitize(array &$data): void
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $this->sanitize($value);
            } elseif (is_string($value)) {
                $value = trim($value);
            }
        }
    }
}
