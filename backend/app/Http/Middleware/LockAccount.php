<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LockAccount
{
    /**
     * Lock akun setelah 5x gagal login dalam 15 menit (PRD 0.3).
     */
    public function handle(Request $request, Closure $next)
    {
        $email = $request->input('email');
        if (! $email) {
            return $next($request);
        }

        $key = "auth_lockout:{$email}";
        $lock = Cache::get($key);

        if ($lock && $lock['count'] >= 5) {
            $remainingSeconds = 900 - (time() - $lock['first_attempt_at']);
            if ($remainingSeconds > 0) {
                abort(429, 'Akun terkunci selama '.ceil($remainingSeconds / 60).' menit lagi. Hubungi admin.');
            }
            Cache::forget($key);
        }

        return $next($request);
    }
}
