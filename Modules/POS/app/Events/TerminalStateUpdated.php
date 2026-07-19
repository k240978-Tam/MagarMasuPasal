<?php

namespace Modules\POS\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired on every cart mutation and pushed over Reverb to both the Cashier
 * POS and the Customer Display for the same terminal — the only channel
 * connecting the two screens (docs/architecture/01-system-architecture.md §1.5).
 * ShouldBroadcastNow (not queued): this must feel instant, not wait for a
 * queue worker to pick it up.
 */
class TerminalStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public string $terminalPublicId,
        public array $state,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('terminal.'.$this->terminalPublicId)];
    }

    public function broadcastAs(): string
    {
        return 'terminal.state.updated';
    }

    public function broadcastWith(): array
    {
        return $this->state;
    }
}
