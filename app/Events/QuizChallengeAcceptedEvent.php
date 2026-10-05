<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizChallengeAcceptedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $gameSessionId,
        public int $challengerId
    ) {}

    public function broadcastOn(): array
    {
        // যে চ্যালেঞ্জ পাঠিয়েছিল (Challenger) তার প্রাইভেট চ্যানেলে পাঠানো হবে
        return [
            new PrivateChannel('App.Models.User.' . $this->challengerId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'QuizChallengeAccepted';
    }
}