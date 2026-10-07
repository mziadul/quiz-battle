<?php

namespace App\Http\Controllers\Api;

use App\Events\BattleChallengeEvent;
use App\Events\QuizMatchFinishedEvent;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Question;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuizMatchController extends Controller
{
    /**
     * 🎯 ১. ক্যাটাগরি ফেচ করার এপিআই
     */
    public function getCategories(): JsonResponse
    {
        try {
            $categories = Category::all();
            return response()->json([
                'status' => 'SUCCESS',
                'categories' => $categories,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Join queue, cancel abandoned sessions, and match players cleanly.
     */
    public function joinQueue(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'category_id' => ['required', 'integer', 'exists:categories,id'],
            ]);

            $userId = (int) auth()->id();
            $categoryId = (int) $validated['category_id'];

            // 🔑 ১. ইউজার আগে তৈরি করা কোনো পুরনো অকার্যকর/ফাঁকা waiting সেশন থাকলে তা বাতিল করা
            GameSession::where('player1_id', $userId)
                ->where('category_id', $categoryId)
                ->where('status', 'waiting')
                ->whereNull('player2_id')
                ->where('created_at', '<', now()->subSeconds(30)) // ৩০ সেকেন্ডের বেশি পুরনো
                ->update(['status' => 'cancelled']);

            // Step 2: অলরেডি চালু থাকা active সেশন থাকলে সেটিই রিটার্ন করবে
            $activeSession = GameSession::where('category_id', $categoryId)
                ->where(function ($q) use ($userId) {
                    $q->where('player1_id', $userId)->orWhere('player2_id', $userId);
                })
                ->where('status', 'active')
                ->latest()
                ->first();

            if ($activeSession) {
                return response()->json([
                    'status' => 'MATCH_FOUND',
                    'session_id' => $activeSession->id,
                    'message' => 'Match active! Transitioning to arena...',
                ]);
            }

            // Step 3: অন্য কারোর প্রস্তুত থাকা সর্বশেষ waiting সেশনে জয়েন করা
            $opponentSession = GameSession::where('category_id', $categoryId)
                ->where('status', 'waiting')
                ->where('player1_id', '!=', $userId)
                ->whereNull('player2_id')
                ->latest()
                ->first();

            if ($opponentSession) {
                $opponentSession->update([
                    'player2_id' => $userId,
                    'status' => 'active',
                    'started_at' => now(),
                ]);

                $this->assignQuestionsToSession($opponentSession->id, $categoryId);

                return response()->json([
                    'status' => 'MATCH_FOUND',
                    'session_id' => $opponentSession->id,
                    'message' => 'Opponent found! Starting match...',
                ]);
            }

            // Step 4: নিজের একটিমাত্র রিসেন্ট waiting সেশন থাকলে সেটিই ফেরত পাঠানো
            $myWaitingSession = GameSession::where('category_id', $categoryId)
                ->where('player1_id', $userId)
                ->where('status', 'waiting')
                ->whereNull('player2_id')
                ->latest()
                ->first();

            if ($myWaitingSession) {
                return response()->json([
                    'status' => 'WAITING',
                    'session_id' => $myWaitingSession->id,
                    'message' => 'Searching for an opponent...',
                ]);
            }

            // Step 5: নতুন সেশন তৈরি
            $newSession = GameSession::create([
                'category_id' => $categoryId,
                'player1_id' => $userId,
                'status' => 'waiting',
            ]);

            $this->assignQuestionsToSession($newSession->id, $categoryId);

            return response()->json([
                'status' => 'WAITING',
                'session_id' => $newSession->id,
                'message' => 'Searching for an opponent...',
            ]);

        } catch (Exception $e) {
            Log::error('JoinQueue Error: '.$e->getMessage());

            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ❌ ৩. ম্যাচমেকিং ক্যান্সেল করা
     */
    public function cancelQueue(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            GameSession::where('player1_id', $userId)
                ->where('status', 'waiting')
                ->whereNull('player2_id')
                ->update(['status' => 'cancelled']);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Queue cancelled successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get match questions and calculate elapsed match time.
     */
    public function getMatchQuestions(int $sessionId): JsonResponse
    {
        try {
            $session = GameSession::find($sessionId);

            if (! $session) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Session not found.',
                ], 404);
            }

            if ($session->status === 'completed') {
                return response()->json([
                    'status' => 'SUCCESS',
                    'session_status' => 'completed',
                    'results' => [
                        'winner_id' => $session->winner_id,
                        'player1_score' => $session->player1_score ?? 0,
                        'player2_score' => $session->player2_score ?? 0,
                    ],
                ]);
            }

            // 🔑 অপোনেন্ট জয়েন না করলে (waiting/pending বা null player2_id) কাউন্টডাউন বন্ধ রাখতে বলা
            if (! $session->started_at || ! $session->player2_id || in_array($session->status, ['waiting', 'pending'], true)) {
                return response()->json([
                    'status' => 'SUCCESS',
                    'session_status' => $session->status,
                    'is_waiting' => true,
                    'message' => 'Waiting for opponent to accept or join...',
                ]);
            }

            $startedAt = \Carbon\Carbon::parse($session->started_at);
            $elapsedSeconds = (int) $startedAt->diffInSeconds(now());
            $totalMatchTime = 50;

            if ($elapsedSeconds >= $totalMatchTime) {
                if ($session->player1_score > $session->player2_score) {
                    $session->winner_id = $session->player1_id;
                } elseif ($session->player2_score > $session->player1_score) {
                    $session->winner_id = $session->player2_id;
                } else {
                    $session->winner_id = null;
                }

                $session->status = 'completed';
                $session->save();

                return response()->json([
                    'status' => 'SUCCESS',
                    'session_status' => 'completed',
                    'results' => [
                        'winner_id' => $session->winner_id,
                        'player1_score' => $session->player1_score ?? 0,
                        'player2_score' => $session->player2_score ?? 0,
                    ],
                ]);
            }

            $questionIndex = (int) floor($elapsedSeconds / 10);
            $remainingSecForCurrentQ = 10 - ($elapsedSeconds % 10);

            $questionIds = DB::table('game_session_questions')
                ->where('game_session_id', $sessionId)
                ->orderBy('sort_order', 'asc')
                ->pluck('question_id');

            $questions = Question::whereIn('id', $questionIds)->get();

            $formattedQuestions = $questions->map(function ($q) {
                return [
                    'id' => $q->id,
                    'question_text' => $q->question_text,
                    'options' => is_string($q->options) ? json_decode($q->options, true) : $q->options,
                    'time_limit_sec' => 10,
                ];
            });

            return response()->json([
                'status' => 'SUCCESS',
                'session_status' => $session->status,
                'is_waiting' => false,
                'current_index' => $questionIndex,
                'remaining_seconds' => $remainingSecForCurrentQ,
                'questions' => $formattedQuestions,
            ]);

        } catch (Exception $e) {
            Log::error('GetMatchQuestions Error: '.$e->getMessage());

            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 📝 ৫. উত্তর সাবমিট করা
     */
    public function submitAnswers(Request $request, int $sessionId): JsonResponse
    {
        try {
            $session = GameSession::findOrFail($sessionId);
            $userId = auth()->id();
            $answers = $request->input('answers', []);

            $totalScore = 0;

            foreach ($answers as $ans) {
                $question = Question::find($ans['question_id']);
                $isCorrect = false;
                $points = 0;

                if ($question && isset($ans['selected_option']) && $question->correct_option === $ans['selected_option']) {
                    $isCorrect = true;
                    $points = 10;
                    $totalScore += $points;
                }

                DB::table('game_answers')->insert([
                    'game_session_id' => $sessionId,
                    'user_id' => $userId,
                    'question_id' => $ans['question_id'],
                    'selected_option' => $ans['selected_option'] ?? null,
                    'time_taken' => $ans['time_taken'] ?? 0,
                    'is_correct' => $isCorrect,
                    'score' => $points,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ((int) $session->player1_id === (int) $userId) {
                $session->player1_score = $totalScore;
            } else {
                $session->player2_score = $totalScore;
            }
            $session->save();

            if (! is_null($session->player1_score) && ! is_null($session->player2_score)) {

                if ($session->player1_score > $session->player2_score) {
                    $session->winner_id = $session->player1_id;
                } elseif ($session->player2_score > $session->player1_score) {
                    $session->winner_id = $session->player2_id;
                } else {
                    $session->winner_id = null; // ড্র
                }

                $session->status = 'completed';
                $session->ended_at = now();
                $session->save();

                $resultData = [
                    'winner_id' => $session->winner_id,
                    'player1_score' => $session->player1_score,
                    'player2_score' => $session->player2_score,
                ];

                try {
                    broadcast(new QuizMatchFinishedEvent($session->id, $resultData));
                } catch (Exception $e) {
                    Log::error('Result Broadcast Error: ' . $e->getMessage());
                }

                return response()->json([
                    'status' => 'COMPLETED',
                    'results' => $resultData,
                ]);
            }

            return response()->json([
                'status' => 'WAITING_OPPONENT',
                'message' => 'Answers saved. Waiting for opponent to finish.',
            ]);

        } catch (Exception $e) {
            Log::error('SubmitAnswers Error: ' . $e->getMessage());
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 🛠️ প্রাইভেট হেল্পার: সেশনে প্রশ্ন নির্ধারণ করা
     */
    private function assignQuestionsToSession(int $sessionId, int $categoryId): void
    {
        $exists = DB::table('game_session_questions')->where('game_session_id', $sessionId)->exists();
        if ($exists) {
            return;
        }

        $questions = Question::where('category_id', $categoryId)->inRandomOrder()->limit(5)->pluck('id');

        foreach ($questions as $index => $qId) {
            DB::table('game_session_questions')->insert([
                'game_session_id' => $sessionId,
                'question_id' => $qId,
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function getRecentOpponents(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            // ইউজার যে যে সেশনে প্লেয়ার ১ বা ২ ছিল সেগুলো থেকে অপোনেন্ট আইডি বের করা
            $sessions = GameSession::where(function ($q) use ($userId) {
                $q->where('player1_id', $userId)->orWhere('player2_id', $userId);
            })
            ->whereNotNull('player2_id')
            ->latest('updated_at')
            ->limit(20)
            ->get();

            $opponentIds = [];
            foreach ($sessions as $session) {
                $oppId = ($session->player1_id == $userId) ? $session->player2_id : $session->player1_id;
                if ($oppId && !in_array($oppId, $opponentIds)) {
                    $opponentIds[] = $oppId;
                }
            }

            $opponents = \App\Models\User::whereIn('id', array_slice($opponentIds, 0, 5))
                ->select('id', 'name', 'email')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'avatar_text' => strtoupper(substr($u->name, 0, 2)),
                    ];
                });

            return response()->json([
                'status' => 'SUCCESS',
                'opponents' => $opponents
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Send a direct battle challenge to a user by email.
     */
    public function sendFriendChallenge(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => ['required', 'email'],
                'category_id' => ['required', 'integer', 'exists:categories,id'],
            ]);

            /** @var \App\Models\User $currentUser */
            $currentUser = auth()->user();
            $userId = $currentUser->id;

            $targetUser = User::where('email', $validated['email'])->first();

            if (! $targetUser) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'User with this email not found.',
                ], 404);
            }

            if ($targetUser->id === $userId) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'You cannot challenge yourself.',
                ], 422);
            }

            $session = GameSession::create([
                'category_id' => $validated['category_id'],
                'player1_id' => $userId,
                'player2_id' => $targetUser->id,
                'status' => 'pending',
            ]);

            $this->assignQuestionsToSession($session->id, (int) $validated['category_id']);

            try {
                broadcast(new BattleChallengeEvent($targetUser->id, [
                    'session_id' => $session->id,
                    'challenger_name' => $currentUser->name,
                    'category_id' => (int) $validated['category_id'],
                ]));
            } catch (Exception $e) {
                Log::error('Broadcast Challenge Error: '.$e->getMessage());
            }

            return response()->json([
                'status' => 'WAITING',
                'session_id' => $session->id,
                'message' => 'Challenge sent successfully.',
            ]);
        } catch (Exception $e) {
            Log::error('SendFriendChallenge Error: '.$e->getMessage());

            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Respond to an incoming friend battle challenge.
     */
    public function respondFriendChallenge(Request $request, int $sessionId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'action' => ['required', 'string', 'in:accept,decline'],
            ]);

            $session = GameSession::findOrFail($sessionId);
            $userId = auth()->id();

            if ((int) $session->player2_id !== (int) $userId) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Unauthorized action.',
                ], 403);
            }

            if ($validated['action'] === 'accept') {
                $session->update([
                    'status' => 'active',
                    'started_at' => now(),
                ]);

                return response()->json([
                    'status' => 'MATCH_FOUND',
                    'session_id' => $session->id,
                ]);
            }

            $session->update(['status' => 'cancelled']);

            return response()->json([
                'status' => 'CANCELLED',
                'message' => 'Challenge declined.',
            ]);
        } catch (Exception $e) {
            Log::error('RespondFriendChallenge Error: '.$e->getMessage());

            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}