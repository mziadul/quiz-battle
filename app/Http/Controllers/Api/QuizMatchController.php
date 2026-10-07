<?php

namespace App\Http\Controllers\Api;

use App\Events\QuizMatchFinishedEvent;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Question;
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
     * ⚔️ ২. ম্যাচমেকিং কিউতে জয়েন করা ও পেয়ারিং মেকানিজম
     */
    public function joinQueue(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'category_id' => 'required|integer',
            ]);

            $userId = auth()->id();
            $categoryId = (int) $request->category_id;

            // Step A: ইউজার ১ কি অলরেডি কোনো active/waiting সেশনে আছে?
            $mySession = GameSession::where('category_id', $categoryId)
                ->where(function ($q) use ($userId) {
                    $q->where('player1_id', $userId)->orWhere('player2_id', $userId);
                })
                ->whereIn('status', ['waiting', 'active', 'pending'])
                ->latest()
                ->first();

            if ($mySession) {
                // প্লেয়ার ২ কি এসে গেছে?
                if ($mySession->player2_id) {
                    if ($mySession->status === 'waiting') {
                        $mySession->update([
                            'status' => 'active',
                            'started_at' => now(),
                        ]);
                    }

                    return response()->json([
                        'status' => 'MATCH_FOUND',
                        'session_id' => $mySession->id,
                        'message' => 'Match found! Starting arena...',
                    ]);
                }

                // ইউজার ১ এখনও অপোনেন্টের জন্য ওয়েট করছে
                return response()->json([
                    'status' => 'WAITING',
                    'session_id' => $mySession->id,
                    'message' => 'Searching for an opponent...',
                ]);
            }

            // Step B: এই ক্যাটাগরিতে অন্য কারো কোনো WAITING সেশন আছে কিনা?
            $opponentSession = GameSession::where('category_id', $categoryId)
                ->where('status', 'waiting')
                ->where('player1_id', '!=', $userId)
                ->whereNull('player2_id')
                ->first();

            if ($opponentSession) {
                // প্লেয়ার ২ এসে প্লেয়ার ১ এর সাথে যুক্ত হলো
                $opponentSession->update([
                    'player2_id' => $userId,
                    'status' => 'active',
                    'started_at' => now(),
                ]);

                // সেশনের জন্য ৫টি র‍্যান্ডম প্রশ্ন অ্যাসাইন করা (যদি আগে তৈরি না হয়ে থাকে)
                $this->assignQuestionsToSession($opponentSession->id, $categoryId);

                return response()->json([
                    'status' => 'MATCH_FOUND',
                    'session_id' => $opponentSession->id,
                    'message' => 'Match found! Starting arena...',
                ]);
            }

            // Step C: কেউ না থাকলে নতুন WAITING সেশন তৈরি
            $newSession = GameSession::create([
                'category_id' => $categoryId,
                'player1_id' => $userId,
                'status' => 'waiting',
            ]);

            // সেশনের প্রশ্ন আগেই প্রস্তুত করে রাখা
            $this->assignQuestionsToSession($newSession->id, $categoryId);

            return response()->json([
                'status' => 'WAITING',
                'session_id' => $newSession->id,
                'message' => 'Searching for an opponent...',
            ]);

        } catch (Exception $e) {
            Log::error('JoinQueue Error: ' . $e->getMessage());
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
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
     * ❓ 8. ম্যাচের জন্য প্রশ্ন এবং অতিক্রান্ত সময় বের করা
     */
    public function getMatchQuestions(int $sessionId): JsonResponse
    {
        try {
            $session = GameSession::find($sessionId);

            if (! $session) {
                return response()->json(['status' => 'ERROR', 'message' => 'Session not found'], 404);
            }

            // ১. যদি ম্যাচ আগেই কমপ্লিট হয়ে থাকে
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

            // 🔑 ২. ম্যাচ যদি এখনো WAITING এ থাকে (অর্থাৎ ২য় প্লেয়ার এখনো আসেনি)
            if (! $session->started_at || $session->status === 'waiting') {
                return response()->json([
                    'status' => 'WAITING_OPPONENT',
                    'session_status' => 'waiting',
                    'message' => 'Waiting for second player to join...',
                ]);
            }

            // ৩. Elapsed Time (অতিক্রান্ত সময়) হিসাব করা (শুধুমাত্র started_at থাকার পর)
            $startedAt = \Carbon\Carbon::parse($session->started_at);
            $elapsedSeconds = (int) $startedAt->diffInSeconds(now());
            $totalMatchTime = 50; // ৫টি প্রশ্ন * ১০ সেকেন্ড = ৫০ সেকেন্ড

            // ৫০ সেকেন্ড পার হয়ে গেলে অটোমেটিক গেম ওভার
            if ($elapsedSeconds >= $totalMatchTime) {
                // ড্র বা বিজয়ী নির্ধারণ
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

            // ৪. অতিক্রান্ত সময়ের ওপর ভিত্তি করে বর্তমান প্রশ্ন ও টাইমার বের করা
            $questionIndex = (int) floor($elapsedSeconds / 10);
            $remainingSecForCurrentQ = 10 - ($elapsedSeconds % 10);

            // প্রশ্নসমূহ ফেচ করা
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
                'current_index' => $questionIndex,
                'remaining_seconds' => $remainingSecForCurrentQ,
                'questions' => $formattedQuestions,
            ]);

        } catch (Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
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
}