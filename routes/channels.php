<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use App\Models\GameSession;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('online-users', function (User $user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
    ];
});

Broadcast::channel('game-session.{sessionId}', function ($user, int $sessionId) {
    $session = GameSession::find($sessionId);
    if (! $session) return false;

    return (int)$user->id === (int)$session->player1_id || (int)$user->id === (int)$session->player2_id;
});
