<?php

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('online-users', function ($user) {
    if ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username ?? $user->email,
            'email' => $user->email,
        ];
    }

    return false;
});

Broadcast::channel('game-session.{sessionId}', function ($user, int $sessionId) {
    $session = GameSession::find($sessionId);
    if (! $session) {
        return false;
    }

    return (int) $user->id === (int) $session->player1_id || (int) $user->id === (int) $session->player2_id;
});