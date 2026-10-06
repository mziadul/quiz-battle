<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GameMatchController extends Controller
{
    /**
     * Quick Match খোঁজা বা তৈরি করা
     */
    public function findQuickMatch(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
        ]);

        $userId = Auth::id();
        $categoryId = $request->category_id;

        // ১. একই ক্যাটাগরিতে আগে থেকে অপেক্ষা করা কোনো প্লেয়ার আছে কি না দেখা
        $waitingSession = GameSession::where('category_id', $categoryId)
            ->where('status', 'waiting')
            ->where('player1_id', '!=', $userId)
            ->first();

        if ($waitingSession) {
            // অপোনেন্ট পাওয়া গেছে, সেশনটি আপডেট করা
            $waitingSession->update([
                'player2_id' => $userId,
                'status'     => 'pending', // প্রতিপক্ষের একসেপ্টের জন্য ওয়েট করা হচ্ছে
            ]);

            // TODO: এখানে Player 1-কে WebSockets/Notification দিয়ে জানানো হবে (যেমন: ChallengeReceived)

            return response()->json([
                'message'      => 'Opponent found! Challenge sent.',
                'game_session' => $waitingSession->load(['player1', 'player2', 'category']),
            ], 200);
        }

        // ২. কোনো ওয়েটিং প্লেয়ার না থাকলে নতুন ওয়েটিং সেশন তৈরি করা
        $newSession = GameSession::create([
            'category_id' => $categoryId,
            'player1_id'  => $userId,
            'status'      => 'waiting',
        ]);

        return response()->json([
            'message'      => 'Searching for an opponent...',
            'game_session' => $newSession->load(['player1', 'category']),
        ], 201);
    }

    /**
     * চ্যালেঞ্জ গ্রহণ (Accept Match)
     */
    public function acceptMatch(Request $request)
    {
        $request->validate([
            'game_session_id' => 'required|exists:game_sessions,id',
        ]);

        $session = GameSession::where('id', $request->game_session_id)
            ->where('status', 'pending')
            ->firstOrFail();

        $session->update([
            'status'     => 'active',
            'started_at' => now(),
        ]);

        // TODO: ব্রডকাস্ট ইভেন্ট পাঠানো যেন উভয় প্লেয়ার একসাথে প্রশ্ন পাওয়া শুরু করে

        return response()->json([
            'message'      => 'Match started successfully!',
            'game_session' => $session,
        ], 200);
    }

    /**
     * চ্যালেঞ্জ বাতিল (Decline/Cancel Match)
     */
    public function declineMatch(Request $request)
    {
        $request->validate([
            'game_session_id' => 'required|exists:game_sessions,id',
        ]);

        $session = GameSession::where('id', $request->game_session_id)->firstOrFail();

        $session->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Match cancelled.',
        ], 200);
    }
}