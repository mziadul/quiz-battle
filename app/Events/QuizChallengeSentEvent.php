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
        // নির্দিষ্ট টার্গেট প্লেয়ারের প্রাইভেট চ্যানেলে ব্রডকাস্ট হবে
        return [
            new PrivateChannel('App.Models.User.' . $this->targetUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'QuizChallengeReceived';
    }
}