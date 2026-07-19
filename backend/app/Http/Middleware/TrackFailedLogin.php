<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrackFailedLogin
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $email = $request->input('email');
        if (! $email) {
            return $response;
        }

        $key = "auth_lockout:{$email}";

        if ($response->status() === 422 || $response->status() === 401) {
            $lock = Cache::get($key, [
                'count' => 0,
                'first_attempt_at' => time(),
            ]);

            $lock['count']++;
            Cache::put($key, $lock, 900);
        }

        return $response;
    }
}
