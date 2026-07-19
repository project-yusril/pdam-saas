<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DataConsent
{
    /**
     * GDPR / UU PDP: catat consent pemrosesan data.
     * PRD 15.B — DIPAKE UNTUK LOG PERSETUJUAN PDP.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->hasHeader('X-Data-Consent') && Auth::check()) {
            $requestActivityLogData = [
                'pdam_org_id' => Auth::user()->pdam_org_id,
                'user_id' => Auth::user()->id,
                'actor_type' => 'user',
                'action' => 'data_consent_granted',
                'ip_address' => $request->ip(),
            ];

            ActivityLog::create($requestActivityLogData);
        }

        return $response;
    }
}
