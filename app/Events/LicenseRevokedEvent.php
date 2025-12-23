<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LicenseRevokedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $licenseUuid
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("license.{$this->licenseUuid}")];
    }

    public function broadcastAs(): string
    {
        return 'license.revoked';
    }

    public function broadcastWith(): array
    {
        return [
            'revoked_at'   => now()->toISOString(),
        ];
    }
}
