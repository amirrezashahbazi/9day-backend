<?php

namespace App\Events;

use App\Models\AccessControlLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccessControlLogReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param  array<string, mixed>  $gate */
    public function __construct(
        public AccessControlLog $log,
        public array $gate = [],
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('access-control-logs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'access-control.log.received';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $payload = is_array($this->log->payload) ? $this->log->payload : [];
        $resolved = is_array($payload['resolved'] ?? null) ? $payload['resolved'] : [];

        $kind = $this->gate['kind']
            ?? ((isset($payload['source']) && $payload['source'] === 'set_user_info') ? 'device_command' : 'traffic');

        return [
            'id' => $this->log->id,
            'payload' => $this->log->payload,
            'source_ip' => $this->log->source_ip,
            'created_at' => $this->log->created_at?->toIso8601String(),
            'card_number' => $this->gate['card_number'] ?? ($resolved['card_number'] ?? null),
            'user_name' => $this->gate['user_name'] ?? ($resolved['user_name'] ?? null),
            'allowed' => array_key_exists('allowed', $this->gate)
                ? (bool) $this->gate['allowed']
                : (bool) ($resolved['allowed'] ?? ($kind !== 'device_command')),
            'reason' => $this->gate['reason'] ?? ($resolved['reason'] ?? null),
            'course_title' => $this->gate['course_title'] ?? ($resolved['course_title'] ?? null),
            'sessions_remaining' => $this->gate['sessions_remaining']
                ?? ($resolved['sessions_remaining'] ?? null),
            'kind' => $kind,
        ];
    }
}
