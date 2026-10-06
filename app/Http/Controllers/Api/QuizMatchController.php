<?php

namespace App\Http\Controllers\Api;

use App\Events\QuizMatchFinishedEvent;
use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Question;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuizMatchController extends Controller
{
    public function getMatchQuestions(int $sessionId): JsonResponse
    {
        try {
            $session = GameSession::find($sessionId);

            if (! $session) {
                return response()->json(['status' => 'ERROR', 'message' => 'Session not found'], 404);
            }

            $questionIds = DB::table('game_session_questions')
                ->where('game_session_id', $sessionId)
                ->orderBy('sort_order', 'asc')
                ->pluck('question_id');

            if ($questionIds->isEmpty()) {
                $questions = Question::where('category_id', $session->category_id)->inRandomOrder()->limit(5)->get();
            } else {
                $questions = Question::whereIn('id', $questionIds)->get();
            }

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
                'questions' => $formattedQuestions,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function submitAnswers(Request $request, int $sessionId): JsonResponse
    {
        try {
            $session = GameSession::findOrFail($sessionId);
            $userId = auth()->id();
            $answers = $request->input('answers', []);

            $totalScore = 0;

            // ১. game_answers টেবিলের কলাম নেম অনুযায়ী ডাটা সেভ
            foreach ($answers as $ans) {
                $question = Question::find($ans['question_id']);
                $isCorrect = false;
                $points = 0;

                if ($question && isset($ans['selected_option']) && $question->correct_option === $ans['selected_option']) {
                    $isCorrect = true;
                    $points = 10;
                    $totalScore += $points;
                }

                // মাইগ্রেশনের সাথে মিলিয়ে schema অনুযায়ী Insert
                DB::table('game_answers')->insert([
                    'game_session_id' => $sessionId,
                    'user_id' => $userId,
                    'question_id' => $ans['question_id'],
                    'selected_option' => $ans['selected_option'] ?? null,
                    'time_taken' => $ans['time_taken'] ?? 0,
                    'is_correct' => $isCorrect,
                    'score' => $points, // 👈 points_earned এর বদলে score
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // ২. প্লেয়ার ১ বা ২ এর স্কোর আপডেট করা
            if ((int)$session->player1_id === (int)$userId) {
                $session->player1_score = $totalScore;
            } else {
                $session->player2_score = $totalScore;
            }
            $session->save();

            // ৩. উভয় প্লেয়ারের উত্তর শেষ হলে বিজয়ী নির্ধারণ
            if (!is_null($session->player1_score) && !is_null($session->player2_score)) {
                
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

                // লাইভ ব্রডকাস্ট
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
}