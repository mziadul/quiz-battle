<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizMatchFinishedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $gameSessionId,
        public array $results
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('game-session.' . $this->gameSessionId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'results' => $this->results,
        ];
    }

    public function broadcastAs(): string
    {
        return 'QuizMatchFinished';
    }
}