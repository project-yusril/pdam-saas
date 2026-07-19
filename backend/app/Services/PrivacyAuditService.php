<?php

namespace App\Services;

use App\Models\PrivacyAuditEvent;
use App\Models\PrivacyPurgeRequest;

class PrivacyAuditService
{
    public function append(PrivacyPurgeRequest $purgeRequest, int $actorId, string $eventType, array $payload): PrivacyAuditEvent
    {
        $previousHash = PrivacyAuditEvent::where('pdam_org_id', $purgeRequest->pdam_org_id)
            ->orderByDesc('id')
            ->value('event_hash');
        ksort($payload);
        $hashInput = implode('|', [
            $previousHash ?? '',
            $purgeRequest->public_id,
            $actorId,
            $eventType,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        return PrivacyAuditEvent::create([
            'pdam_org_id' => $purgeRequest->pdam_org_id,
            'privacy_purge_request_id' => $purgeRequest->id,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'payload' => $payload,
            'previous_hash' => $previousHash,
            'event_hash' => hash('sha256', $hashInput),
        ]);
    }
}
