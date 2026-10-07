<?php

use App\Http\Controllers\Api\GameMatchController;
use App\Http\Controllers\Api\QuizMatchController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $token = $user->createToken('mobile-app')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username ?? $user->email,
        ],
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/match/quick-find', [GameMatchController::class, 'findQuickMatch']);
    Route::post('/match/accept', [GameMatchController::class, 'acceptMatch']);
    Route::post('/match/decline', [GameMatchController::class, 'declineMatch']);
});

// 🎯 Quiz Routes All Mapped to Unified QuizMatchController
Route::middleware(['auth:sanctum'])->prefix('quiz')->group(function () {
    Route::get('/categories', [QuizMatchController::class, 'getCategories']);
    Route::post('/join-queue', [QuizMatchController::class, 'joinQueue']);
    Route::post('/cancel-queue', [QuizMatchController::class, 'cancelQueue']);

    Route::get('/questions/{session}', [QuizMatchController::class, 'getMatchQuestions']);
    Route::post('/submit-answers/{session}', [QuizMatchController::class, 'submitAnswers']);
    Route::get('/recent-opponents', [QuizMatchController::class, 'getRecentOpponents']);

    Route::post('/friend-challenge/send', [QuizMatchController::class, 'sendFriendChallenge']);
    Route::post('/friend-challenge/{session}/respond', [QuizMatchController::class, 'respondFriendChallenge']);
});

Broadcast::routes(['middleware' => ['auth:sanctum']]);