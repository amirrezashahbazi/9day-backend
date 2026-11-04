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

    public function __construct(public AccessControlLog $log)
    {
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

        return [
            'id' => $this->log->id,
            'payload' => $this->log->payload,
            'source_ip' => $this->log->source_ip,
            'created_at' => $this->log->created_at?->toIso8601String(),
            'card_number' => isset($resolved['card_number']) ? (string) $resolved['card_number'] : null,
            'user_name' => isset($resolved['user_name']) ? (string) $resolved['user_name'] : null,
            'kind' => isset($payload['source']) && $payload['source'] === 'set_user_info'
                ? 'device_command'
                : 'traffic',
        ];
    }
}
