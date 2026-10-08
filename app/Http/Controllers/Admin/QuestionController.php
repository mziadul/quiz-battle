<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportQuestionCsvRequest;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Category;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        $questions = Question::query()
            ->with('category:id,name')
            ->when($search, function ($query, $search): void {
                $query->where('question_text', 'like', "%{$search}%");
            })
            ->when($categoryId, function ($query, $categoryId): void {
                $query->where('category_id', $categoryId);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $categories = Category::select('id', 'name')->get();

        return Inertia::render('Admin/Questions/Index', [
            'questions' => $questions,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
            ],
        ]);
    }

    public function store(StoreQuestionRequest $request): RedirectResponse
    {
        Question::create($request->validated());

        return redirect()->back()->with('success', 'Question created successfully.');
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $question->update($request->validated());

        return redirect()->back()->with('success', 'Question updated successfully.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $question->delete();

        return redirect()->back()->with('success', 'Question deleted successfully.');
    }

    /**
     * Download sample CSV format for question bulk upload.
     */
    public function downloadSampleCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sample_questions_upload.csv"',
        ];

        $columns = ['category_id', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'base_point', 'time_limit_sec'];

        $callback = function () use ($columns): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            // Sample rows for user guidance
            fputcsv($file, [
                1,
                'What is the capital of Bangladesh?',
                'Dhaka',
                'Chittagong',
                'Sylhet',
                'Khulna',
                'a',
                100,
                15,
            ]);

            fputcsv($file, [
                1,
                'Which planet is known as the Red Planet?',
                'Earth',
                'Mars',
                'Jupiter',
                'Venus',
                'b',
                100,
                15,
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import questions from CSV file.
     */
    public function importCsv(ImportQuestionCsvRequest $request): RedirectResponse
    {
        $file = $request->file('csv_file');

        if (! $file) {
            return redirect()->back()->with('error', 'Please upload a valid CSV file.');
        }

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return redirect()->back()->with('error', 'Unable to open CSV file.');
        }

        // Header row পড়া
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return redirect()->back()->with('error', 'CSV file is empty.');
        }

        $questionsToInsert = [];
        $now = now();

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                // স্কিপ যদি ফাঁকা রো হয়
                if (empty(array_filter($row))) {
                    continue;
                }

                // Header অনুযায়ী ম্যাপ করা (category_id, question_text, option_a, option_b, option_c, option_d, correct_option, base_point, time_limit_sec)
                $categoryId = ! empty($row[0]) ? (int) $row[0] : null;
                $questionText = trim($row[1] ?? '');
                $optionA = trim($row[2] ?? '');
                $optionB = trim($row[3] ?? '');
                $optionC = trim($row[4] ?? '');
                $optionD = trim($row[5] ?? '');
                $correctOption = strtolower(trim($row[6] ?? 'a'));
                $basePoint = ! empty($row[7]) ? (int) $row[7] : 100;
                $timeLimitSec = ! empty($row[8]) ? (int) $row[8] : 15;

                if (empty($questionText) || empty($optionA) || empty($optionB)) {
                    continue; // প্রয়োজনীয় ডাটা না থাকলে স্কিপ করবে
                }

                $options = [
                    'a' => $optionA,
                    'b' => $optionB,
                    'c' => $optionC,
                    'd' => $optionD,
                ];

                $questionsToInsert[] = [
                    'category_id' => $categoryId,
                    'question_text' => $questionText,
                    'options' => json_encode($options),
                    'correct_option' => $correctOption,
                    'base_point' => $basePoint,
                    'time_limit_sec' => $timeLimitSec,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            fclose($handle);

            if (! empty($questionsToInsert)) {
                Question::insert($questionsToInsert);
                DB::commit();

                return redirect()->back()->with('success', count($questionsToInsert).' questions imported successfully.');
            }

            DB::rollBack();

            return redirect()->back()->with('error', 'No valid questions found in the CSV file.');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'CSV import failed: '.$e->getMessage());
        }
    }
}