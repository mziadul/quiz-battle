<?php

namespace App\Http\Controllers\Api;

use App\Events\MatchFoundEvent;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GameAnswer;
use App\Models\GameSession;
use App\Models\Question;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchmakingController extends Controller
{
    /**
     * Get active quiz categories list for frontend.
     */
    public function getCategories(): JsonResponse
    {
        $categories = Category::all(['id', 'name', 'slug']);

        return response()->json($categories);
    }

    /**
     * Join quick match queue or immediately match with a waiting user.
     */
    public function joinQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
        ]);

        $user = auth()->user();
        $categoryId = (int) $validated['category_id'];

        // Cancel any existing uncompleted waiting/pending matches for this user
        GameSession::where('player1_id', $user->id)
            ->whereIn('status', ['waiting', 'pending'])
            ->update(['status' => 'cancelled']);

        // Check if an opponent is already waiting in the queue for this category
        $waitingSession = GameSession::where('category_id', $categoryId)
            ->where('status', 'waiting')
            ->where('player1_id', '!=', $user->id)
            ->first();

        if ($waitingSession) {
            // Match Found! Assign current user as Player 2
            $waitingSession->update([
                'player2_id' => $user->id,
                'status' => 'active',
                'started_at' => now(),
            ]);

            // Assign 5 questions from this category to the session
            $this->assignCategoryQuestions($waitingSession->id, $categoryId);

            // Broadcast match found to Player 1 (who was waiting in queue)
            try {
                broadcast(new MatchFoundEvent($waitingSession->id, $waitingSession->player1_id));
            } catch (Exception $e) {
                Log::error('MatchFound Broadcast Error: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'MATCH_FOUND',
                'session_id' => $waitingSession->id,
                'message' => 'Match found! Starting arena...',
            ]);
        }

        // No opponent found -> Create new waiting session queue
        $newSession = GameSession::create([
            'category_id' => $categoryId,
            'player1_id' => $user->id,
            'status' => 'waiting',
        ]);

        return response()->json([
            'status' => 'WAITING',
            'session_id' => $newSession->id,
            'message' => 'Searching for an opponent...',
        ]);
    }

    /**
     * Cancel match queue search.
     */
    public function cancelQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:game_sessions,id',
        ]);

        GameSession::where('id', $validated['session_id'])
            ->where('player1_id', auth()->id())
            ->where('status', 'waiting')
            ->update(['status' => 'cancelled']);

        return response()->json([
            'status' => 'CANCELLED',
            'message' => 'Queue cancelled successfully.',
        ]);
    }

    /**
     * Pre-assign 5 category-specific questions for a match session.
     */
    private function assignCategoryQuestions(int $sessionId, int $categoryId): void
    {
        GameAnswer::where('game_session_id', $sessionId)->delete();
        DB::table('game_session_questions')->where('game_session_id', $sessionId)->delete();

        // Pick 5 random questions (priority to category_id, fallbacks to general if low on category count)
        $questions = Question::where('category_id', $categoryId)->inRandomOrder()->limit(5)->get();

        if ($questions->count() < 5) {
            $questions = Question::inRandomOrder()->limit(5)->get();
        }

        foreach ($questions as $index => $q) {
            DB::table('game_session_questions')->insert([
                'game_session_id' => $sessionId,
                'question_id' => $q->id,
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}