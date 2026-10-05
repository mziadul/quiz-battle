<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\QuizChallengeAcceptedEvent;
use App\Events\QuizChallengeSentEvent;
use App\Events\QuizMatchFinishedEvent;
use App\Models\GameAnswer;
use App\Models\GameSession;
use App\Models\Question;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class QuizMatchController extends Controller
{
    /**
     * Search for a player by email or username.
     */
    public function searchUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string',
        ]);

        $searchQuery = trim($validated['query']);

        /** @var User|null $currentUser */
        $currentUser = auth()->user();

        if (! $currentUser) {
            return response()->json(['found' => false, 'message' => 'Unauthenticated.'], 401);
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('id', '!=', $currentUser->id)
            ->where(function ($query) use ($searchQuery): void {
                $query->where('email', $searchQuery)
                    ->orWhere('username', $searchQuery);
            })
            ->first(['id', 'name', 'username', 'email']);

        if (! $user) {
            return response()->json(['found' => false, 'message' => 'User not found.']);
        }

        return response()->json(['found' => true, 'user' => $user]);
    }

    /**
     * Send a challenge invitation and cancel any old pending/active matches between these users.
     */
    public function sendChallenge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_user_id' => 'required|integer|exists:users,id',
        ]);

        /** @var User $challenger */
        $challenger = auth()->user();
        $targetUserId = (int) $validated['target_user_id'];

        // Cancel lingering uncompleted matches between these two players
        GameSession::where(function ($q) use ($challenger, $targetUserId) {
            $q->where('player1_id', $challenger->id)->where('player2_id', $targetUserId);
        })->orWhere(function ($q) use ($challenger, $targetUserId) {
            $q->where('player1_id', $targetUserId)->where('player2_id', $challenger->id);
        })->whereIn('status', ['pending', 'active'])->update(['status' => 'cancelled']);

        /** @var GameSession $session */
        $session = GameSession::create([
            'player1_id' => $challenger->id,
            'player2_id' => $targetUserId,
            'status' => 'pending',
        ]);

        try {
            broadcast(new QuizChallengeSentEvent($challenger, $targetUserId, $session->id));
        } catch (Exception $e) {
            Log::error('Reverb Send Challenge Error: '.$e->getMessage());
        }

        return response()->json([
            'status' => 'CHALLENGE_SENT',
            'session_id' => $session->id,
            'message' => 'Challenge sent! Waiting for response...',
        ]);
    }

    /**
     * Respond to a challenge invitation and pre-assign fixed questions for the session.
     */
    public function respondChallenge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:game_sessions,id',
            'status' => 'required|string|in:accepted,declined',
        ]);

        /** @var GameSession $session */
        $session = GameSession::findOrFail($validated['session_id']);

        if ($validated['status'] === 'accepted') {
            // Cleanup any previous answers/session questions if retried
            GameAnswer::where('game_session_id', $session->id)->delete();
            DB::table('game_session_questions')->where('game_session_id', $session->id)->delete();

            // Lock 5 random questions permanently for this match session
            $randomQuestions = Question::inRandomOrder()->limit(5)->get();
            foreach ($randomQuestions as $index => $q) {
                DB::table('game_session_questions')->insert([
                    'game_session_id' => $session->id,
                    'question_id' => $q->id,
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Set session active with start timestamp
            $session->update([
                'status' => 'active',
                'started_at' => now(),
            ]);

            try {
                broadcast(new QuizChallengeAcceptedEvent($session->id, $session->player1_id));
            } catch (Exception $e) {
                Log::error('Accept Broadcast Error: '.$e->getMessage());
            }

            return response()->json([
                'status' => 'ACCEPTED',
                'session_id' => $session->id,
                'message' => 'Challenge accepted! Redirecting...',
            ]);
        }

        $session->update(['status' => 'cancelled']);

        return response()->json(['status' => 'DECLINED', 'message' => 'Challenge declined.']);
    }

    /**
     * Show the Match Arena page.
     */
    public function showMatch(int $sessionId): InertiaResponse
    {
        /** @var GameSession $session */
        $session = GameSession::with(['player1', 'player2'])->findOrFail($sessionId);

        return Inertia::render('Quiz/MatchArena', [
            'session' => $session,
            'currentUser' => auth()->user(),
        ]);
    }

    /**
     * Fetch fixed questions for the session with elapsed timing information.
     */
    public function getQuestions(int $sessionId): JsonResponse
    {
        try {
            /** @var GameSession $session */
            $session = GameSession::findOrFail($sessionId);

            $sessionQuestions = DB::table('game_session_questions')
                ->where('game_session_id', $session->id)
                ->orderBy('sort_order', 'asc')
                ->get();

            $questionIds = $sessionQuestions->pluck('question_id');
            $questions = Question::whereIn('id', $questionIds)->get()->keyBy('id');

            $formattedQuestions = $sessionQuestions->map(function ($sq) use ($questions) {
                $q = $questions->get($sq->question_id);
                $options = $q?->options ?? [];

                return [
                    'id' => $q->id ?? $sq->question_id,
                    'question_text' => $q?->question_text ?? '',
                    'option_a' => $options['A'] ?? $options['a'] ?? $q?->option_a ?? '',
                    'option_b' => $options['B'] ?? $options['b'] ?? $q?->option_b ?? '',
                    'option_c' => $options['C'] ?? $options['c'] ?? $q?->option_c ?? '',
                    'option_d' => $options['D'] ?? $options['d'] ?? $q?->option_d ?? '',
                    'base_point' => $q?->base_point ?? 10,
                    'time_limit_sec' => $q?->time_limit_sec ?? 10,
                ];
            });

            $results = null;
            if ($session->status === 'completed') {
                $p1Score = (int) GameAnswer::where('game_session_id', $session->id)->where('user_id', $session->player1_id)->sum('score');
                $p2Score = (int) GameAnswer::where('game_session_id', $session->id)->where('user_id', $session->player2_id)->sum('score');

                $results = [
                    'p1_id' => $session->player1_id,
                    'p1_score' => $p1Score,
                    'p2_id' => $session->player2_id,
                    'p2_score' => $p2Score,
                    'winner_id' => $session->winner_id,
                ];
            }

            return response()->json([
                'status' => 'SUCCESS',
                'session_status' => $session->status,
                'started_at' => $session->started_at ?? now()->toDateTimeString(),
                'server_time' => now()->toDateTimeString(),
                'questions' => $formattedQuestions,
                'results' => $results,
            ]);
        } catch (Exception $e) {
            Log::error('Fetch Questions Error: '.$e->getMessage());

            return response()->json(['status' => 'ERROR', 'message' => 'Failed to load questions.'], 500);
        }
    }

    /**
     * Submit player answers, calculate speed bonus scores, and finish the match if both submitted.
     */
    public function submitAnswers(Request $request, int $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'answers' => 'nullable|array',
            'answers.*.question_id' => 'required|integer|exists:questions,id',
            'answers.*.selected_option' => 'nullable|string',
            'answers.*.time_taken' => 'required|integer',
        ]);

        /** @var GameSession $session */
        $session = GameSession::findOrFail($sessionId);
        $userId = (int) auth()->id();
        $userAnswers = $validated['answers'] ?? [];

        foreach ($userAnswers as $ans) {
            /** @var Question|null $question */
            $question = Question::find($ans['question_id']);
            $isCorrect = false;

            if ($question && ! empty($ans['selected_option'])) {
                $correctOption = $question->correct_option ?? $question->answer ?? null;
                if ($correctOption && strtolower((string) $ans['selected_option']) === strtolower((string) $correctOption)) {
                    $isCorrect = true;
                }
            }

            // Base Score: 10. Speed Bonus: max 10 points (10s time limit - time_taken)
            $score = $isCorrect ? (10 + max(0, 10 - (int) $ans['time_taken'])) : 0;

            GameAnswer::updateOrCreate([
                'game_session_id' => $session->id,
                'user_id' => $userId,
                'question_id' => $ans['question_id'],
            ], [
                'selected_option' => $ans['selected_option'],
                'time_taken' => $ans['time_taken'],
                'is_correct' => $isCorrect,
                'score' => $score,
            ]);
        }

        if ($session->status === 'completed' && $session->winner_id !== null) {
            $p1Score = (int) GameAnswer::where('game_session_id', $session->id)->where('user_id', $session->player1_id)->sum('score');
            $p2Score = (int) GameAnswer::where('game_session_id', $session->id)->where('user_id', $session->player2_id)->sum('score');

            return response()->json([
                'status' => 'COMPLETED',
                'results' => [
                    'p1_id' => $session->player1_id,
                    'p1_score' => $p1Score,
                    'p2_id' => $session->player2_id,
                    'p2_score' => $p2Score,
                    'winner_id' => $session->winner_id,
                ],
            ]);
        }

        $p1Submissions = GameAnswer::where('game_session_id', $session->id)->where('user_id', $session->player1_id)->count();
        $p2Submissions = GameAnswer::where('game_session_id', $session->id)->where('user_id', $session->player2_id)->count();

        if ($p1Submissions >= 5 && $p2Submissions >= 5) {
            $p1Score = (int) GameAnswer::where('game_session_id', $session->id)->where('user_id', $session->player1_id)->sum('score');
            $p2Score = (int) GameAnswer::where('game_session_id', $session->id)->where('user_id', $session->player2_id)->sum('score');

            $winnerId = null;
            if ($p1Score > $p2Score) {
                $winnerId = $session->player1_id;
            } elseif ($p2Score > $p1Score) {
                $winnerId = $session->player2_id;
            }

            $session->update([
                'status' => 'completed',
                'winner_id' => $winnerId,
                'ended_at' => now(),
            ]);

            $resultsData = [
                'p1_id' => $session->player1_id,
                'p1_score' => $p1Score,
                'p2_id' => $session->player2_id,
                'p2_score' => $p2Score,
                'winner_id' => $winnerId,
            ];

            try {
                broadcast(new QuizMatchFinishedEvent($session->id, $resultsData));
            } catch (Exception $e) {
                Log::error('Match Broadcast Error: '.$e->getMessage());
            }

            return response()->json([
                'status' => 'COMPLETED',
                'results' => $resultsData,
            ]);
        }

        return response()->json([
            'status' => 'WAITING_FOR_OPPONENT',
            'message' => 'Answers recorded. Waiting for opponent...',
        ]);
    }
}