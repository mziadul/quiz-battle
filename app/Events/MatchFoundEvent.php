<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchFoundEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $gameSessionId;
    public int $targetUserId;

    /**
     * Create a new event instance.
     */
    public function __construct(GameSession|int $gameSession, ?int $targetUserId = null)
    {
        if ($gameSession instanceof GameSession) {
            $this->gameSessionId = $gameSession->id;
            $this->targetUserId = $targetUserId ?? $gameSession->player1_id;
        } else {
            $this->gameSessionId = $gameSession;
            $this->targetUserId = $targetUserId;
        }
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->targetUserId),
        ];
    }

    /**
     * Data to broadcast to the client.
     */
    public function broadcastWith(): array
    {
        return [
            'gameSessionId' => $this->gameSessionId,
        ];
    }

    /**
     * Custom broadcast event name for Echo listener (.MatchFound)
     */
    public function broadcastAs(): string
    {
        return 'MatchFound';
    }
}