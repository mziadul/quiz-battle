<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        Question::create([
            'question_text' => 'What is the capital of Bangladesh?',
            'options' => json_encode(['A' => 'Dhaka', 'B' => 'Chittagong', 'C' => 'Sylhet', 'D' => 'Rajshahi']),
            'correct_option' => 'A',
            'base_point' => 100,
            'time_limit_sec' => 15,
        ]);

        Question::create([
            'question_text' => 'Which framework are we using for backend?',
            'options' => json_encode(['A' => 'Django', 'B' => 'Laravel', 'C' => 'Express', 'D' => 'Spring']),
            'correct_option' => 'B',
            'base_point' => 100,
            'time_limit_sec' => 15,
        ]);
    }
}