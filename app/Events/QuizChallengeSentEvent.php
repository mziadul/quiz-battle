<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizChallengeSentEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $challenger,
        public int $targetUserId,
        public int $gameSessionId
    ) {}

    public function broadcastOn(): array
    {
        \Log::info('--- REVERB EVENT DISPATCHING ---', [
            'target_user_id' => $this->targetUserId,
            'channel' => 'App.Models.User.' . $this->targetUserId,
            'game_session_id' => $this->gameSessionId
        ]);

        return [
            new PrivateChannel('App.Models.User.' . $this->targetUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'QuizChallengeReceived';
    }
}