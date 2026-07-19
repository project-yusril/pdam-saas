<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Integration;
use App\Models\IntegrationLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class IntegrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Integration::when($request->input('provider'), fn ($q, $v) => $q->where('provider', $v));

        return ApiResponse::success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'provider' => ['required', 'in:whatsapp,sms,email,midtrans,fcm,google_vision,custom'],
            'credentials' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
        ]);

        return ApiResponse::success(Integration::create($data), status: 201);
    }

    public function toggle(Request $request, Integration $integration): JsonResponse
    {
        $integration->update(['is_active' => ! $integration->is_active]);

        return ApiResponse::success($integration);
    }

    public function logs(Request $request): JsonResponse
    {
        $query = IntegrationLog::with('integration:id,name')
            ->when($request->input('integration_id'), fn ($q, $v) => $q->where('integration_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function testSend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'integration_id' => ['required', 'integer', 'exists:integrations,id'],
            'to' => ['required', 'string'],
            'message' => ['required', 'string'],
        ]);

        $integration = Integration::findOrFail($data['integration_id']);

        $log = IntegrationLog::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'integration_id' => $integration->id,
            'action' => 'send_test',
            'status' => 'pending',
        ]);

        try {
            if ($integration->provider === 'whatsapp') {
                $resp = Http::withHeaders([
                    'Authorization' => 'Bearer '.($integration->credentials['api_key'] ?? ''),
                ])->post($integration->config['base_url'] ?? '', [
                    'to' => $data['to'],
                    'message' => $data['message'],
                ]);
                $log->update(['status' => $resp->successful() ? 'success' : 'failed', 'http_status' => $resp->status(), 'response_payload' => $resp->body()]);
            } elseif ($integration->provider === 'sms' || $integration->provider === 'email') {
                $log->update(['status' => 'success', 'http_status' => 200]);
            } else {
                $log->update(['status' => 'failed', 'error_message' => 'Unsupported provider']);
            }
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }

        return ApiResponse::success($log);
    }

    public function apiKeys(Request $request): JsonResponse
    {
        $query = ApiKey::when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function createApiKey(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'scopes' => ['nullable', 'array'],
            'rate_limit' => ['nullable', 'integer', 'min:60'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $key = ApiKey::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'name' => $data['name'],
            'key' => hash('sha256', random_bytes(32)),
            'scopes' => $data['scopes'] ?? ['read'],
            'rate_limit' => $data['rate_limit'] ?? 120,
            'expires_at' => $data['expires_at'] ?? now()->addYear(),
        ]);

        return ApiResponse::success($key, status: 201);
    }

    public function revokeApiKey(Request $request, ApiKey $apiKey): JsonResponse
    {
        $apiKey->update(['is_active' => false]);

        return ApiResponse::success($apiKey);
    }

    public function webhook(Request $request): JsonResponse
    {
        $data = $request->validate([
            'integration_id' => ['required', 'integer', 'exists:integrations,id'],
            'payload' => ['required', 'array'],
        ]);

        $integration = Integration::findOrFail($data['integration_id']);

        $log = IntegrationLog::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'integration_id' => $integration->id,
            'action' => 'webhook_received',
            'status' => 'success',
            'request_payload' => json_encode($data['payload']),
        ]);

        return ApiResponse::success(['log_id' => $log->id]);
    }
}
