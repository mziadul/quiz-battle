<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ১. ডাটাবেজ টেবিল ট্রাঙ্কট করা
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Question::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ২. ক্যাটাগরি তালিকা লোড করা
        $categories = Category::all()->keyBy('name');

        if ($categories->isEmpty()) {
            $this->command->error('No categories found! Please run CategorySeeder first.');
            return;
        }

        // ৩. ৮টি নির্দিষ্ট ক্যাটাগরির মাস্টার কুইজ ব্যাংক
        $categorizedBank = [
            'General Knowledge' => [
                ['q' => 'What is the capital city of France?', 'opts' => ['A' => 'Berlin', 'B' => 'Madrid', 'C' => 'Paris', 'D' => 'Rome'], 'ans' => 'C'],
                ['q' => 'Which is the largest ocean on Earth?', 'opts' => ['A' => 'Atlantic Ocean', 'B' => 'Indian Ocean', 'C' => 'Arctic Ocean', 'D' => 'Pacific Ocean'], 'ans' => 'D'],
                ['q' => 'Which country is known as the Land of the Rising Sun?', 'opts' => ['A' => 'China', 'B' => 'Japan', 'C' => 'South Korea', 'D' => 'Thailand'], 'ans' => 'B'],
                ['q' => 'What is the longest river in the world?', 'opts' => ['A' => 'Amazon', 'B' => 'Nile', 'C' => 'Mississippi', 'D' => 'Yangtze'], 'ans' => 'B'],
                ['q' => 'Which is the smallest country in the world by land area?', 'opts' => ['A' => 'Monaco', 'B' => 'Maldives', 'C' => 'Vatican City', 'D' => 'San Marino'], 'ans' => 'C'],
                ['q' => 'Which continent is the Sahara Desert located in?', 'opts' => ['A' => 'Asia', 'B' => 'Africa', 'C' => 'South America', 'D' => 'Australia'], 'ans' => 'B'],
                ['q' => 'What is the official currency of Japan?', 'opts' => ['A' => 'Yuan', 'B' => 'Won', 'C' => 'Yen', 'D' => 'Ringgit'], 'ans' => 'C'],
                ['q' => 'Which country has the most natural lakes in the world?', 'opts' => ['A' => 'Canada', 'B' => 'Russia', 'C' => 'USA', 'D' => 'Brazil'], 'ans' => 'A'],
            ],

            'Sports' => [
                ['q' => 'How many players are on the field for one soccer team in an official match?', 'opts' => ['A' => '9', 'B' => '10', 'C' => '11', 'D' => '12'], 'ans' => 'C'],
                ['q' => 'Which national team won the FIFA World Cup in 2022?', 'opts' => ['A' => 'France', 'B' => 'Brazil', 'C' => 'Argentina', 'D' => 'Croatia'], 'ans' => 'C'],
                ['q' => 'In which sport is the term "Love" used to represent a score of zero?', 'opts' => ['A' => 'Badminton', 'B' => 'Golf', 'C' => 'Tennis', 'D' => 'Cricket'], 'ans' => 'C'],
                ['q' => 'How many rings are on the official Olympic flag?', 'opts' => ['A' => '4', 'B' => '5', 'C' => '6', 'D' => '7'], 'ans' => 'B'],
                ['q' => 'Which country invented Cricket?', 'opts' => ['A' => 'Australia', 'B' => 'India', 'C' => 'England', 'D' => 'South Africa'], 'ans' => 'C'],
            ],

            'Science' => [
                ['q' => 'Which planet in our solar system is known as the Red Planet?', 'opts' => ['A' => 'Venus', 'B' => 'Mars', 'C' => 'Jupiter', 'D' => 'Saturn'], 'ans' => 'B'],
                ['q' => 'What is the chemical symbol for Gold?', 'opts' => ['A' => 'Ag', 'B' => 'Au', 'C' => 'Fe', 'D' => 'Hg'], 'ans' => 'B'],
                ['q' => 'What gas do plants absorb from the atmosphere for photosynthesis?', 'opts' => ['A' => 'Oxygen', 'B' => 'Nitrogen', 'C' => 'Carbon Dioxide', 'D' => 'Hydrogen'], 'ans' => 'C'],
                ['q' => 'What is the hardest natural substance on Earth?', 'opts' => ['A' => 'Gold', 'B' => 'Iron', 'C' => 'Diamond', 'D' => 'Platinum'], 'ans' => 'C'],
                ['q' => 'How many bones are in the adult human body?', 'opts' => ['A' => '206', 'B' => '208', 'C' => '210', 'D' => '300'], 'ans' => 'A'],
                ['q' => 'What speed does light travel at per second (approx.)?', 'opts' => ['A' => '150,000 km/s', 'B' => '300,000 km/s', 'C' => '450,000 km/s', 'D' => '500,000 km/s'], 'ans' => 'B'],
                ['q' => 'Which main organ purifies blood in the human body?', 'opts' => ['A' => 'Heart', 'B' => 'Lungs', 'C' => 'Kidneys', 'D' => 'Liver'], 'ans' => 'C'],
                ['q' => 'What is the chemical formula for water?', 'opts' => ['A' => 'CO2', 'B' => 'H2O', 'C' => 'NaCl', 'D' => 'O2'], 'ans' => 'B'],
            ],

            'Movies' => [
                ['q' => 'Which movie features the song "My Heart Will Go On" by Celine Dion?', 'opts' => ['A' => 'Avatar', 'B' => 'Titanic', 'C' => 'The Bodyguard', 'D' => 'Frozen'], 'ans' => 'B'],
                ['q' => 'Who directed the epic science fiction film "Inception"?', 'opts' => ['A' => 'Steven Spielberg', 'B' => 'Christopher Nolan', 'C' => 'James Cameron', 'D' => 'Quentin Tarantino'], 'ans' => 'B'],
                ['q' => 'Which movie won the Academy Award for Best Picture in 2020 (the first foreign-language film to do so)?', 'opts' => ['A' => '1917', 'B' => 'Parasite', 'C' => 'Roma', 'D' => 'The Irishman'], 'ans' => 'B'],
                ['q' => 'What color is the pill Neo takes in "The Matrix"?', 'opts' => ['A' => 'Blue', 'B' => 'Red', 'C' => 'Green', 'D' => 'Yellow'], 'ans' => 'B'],
            ],

            'Gaming' => [
                ['q' => 'Which company developed the popular game "Minecraft"?', 'opts' => ['A' => 'Mojang Studios', 'B' => 'Epic Games', 'C' => 'Valve', 'D' => 'Ubisoft'], 'ans' => 'A'],
                ['q' => 'What is the best-selling video game console of all time?', 'opts' => ['A' => 'Nintendo DS', 'B' => 'PlayStation 2', 'C' => 'Game Boy', 'D' => 'Xbox 360'], 'ans' => 'B'],
                ['q' => 'In "Super Mario Bros.", what is the name of Mario\'s brother?', 'opts' => ['A' => 'Wario', 'B' => 'Luigi', 'C' => 'Yoshi', 'D' => 'Toad'], 'ans' => 'B'],
                ['q' => 'Which game engine is developed by Epic Games?', 'opts' => ['A' => 'Unity', 'B' => 'Unreal Engine', 'C' => 'Godot', 'D' => 'CryEngine'], 'ans' => 'B'],
            ],

            'Technology' => [
                ['q' => 'Who is considered the father of Computer Science?', 'opts' => ['A' => 'Alan Turing', 'B' => 'Bill Gates', 'C' => 'Steve Jobs', 'D' => 'Charles Babbage'], 'ans' => 'A'],
                ['q' => 'What does HTTP stand for in web technology?', 'opts' => ['A' => 'HyperText Transfer Protocol', 'B' => 'HighText Technical Protocol', 'C' => 'HyperTransfer Text Protocol', 'D' => 'HyperText Test Process'], 'ans' => 'A'],
                ['q' => 'Which operating system uses the Linux kernel and is developed by Google for mobile devices?', 'opts' => ['A' => 'iOS', 'B' => 'Android', 'C' => 'Windows Mobile', 'D' => 'Symbian'], 'ans' => 'B'],
                ['q' => 'In programming, what does the acronym CPU stand for?', 'opts' => ['A' => 'Central Process Unit', 'B' => 'Central Processing Unit', 'C' => 'Computer Primary Unit', 'D' => 'Control Program Utility'], 'ans' => 'B'],
                ['q' => 'Which technology company created the Java programming language?', 'opts' => ['A' => 'Microsoft', 'B' => 'Sun Microsystems', 'C' => 'IBM', 'D' => 'Google'], 'ans' => 'B'],
                ['q' => 'What type of storage is RAM in a computer?', 'opts' => ['A' => 'Non-volatile', 'B' => 'Volatile', 'C' => 'Permanent', 'D' => 'Optical'], 'ans' => 'B'],
            ],

            'History' => [
                ['q' => 'In which year did World War II come to an end?', 'opts' => ['A' => '1942', 'B' => '1945', 'C' => '1948', 'D' => '1950'], 'ans' => 'B'],
                ['q' => 'Who was the first person to walk on the Moon in 1969?', 'opts' => ['A' => 'Buzz Aldrin', 'B' => 'Yuri Gagarin', 'C' => 'Neil Armstrong', 'D' => 'Michael Collins'], 'ans' => 'C'],
                ['q' => 'Who painted the famous artwork Mona Lisa?', 'opts' => ['A' => 'Vincent van Gogh', 'B' => 'Pablo Picasso', 'C' => 'Leonardo da Vinci', 'D' => 'Michelangelo'], 'ans' => 'C'],
                ['q' => 'Which ancient civilization built the Great Pyramids of Giza?', 'opts' => ['A' => 'Romans', 'B' => 'Greeks', 'C' => 'Egyptians', 'D' => 'Mayans'], 'ans' => 'C'],
                ['q' => 'Which empire was ruled by Julius Caesar?', 'opts' => ['A' => 'Ottoman Empire', 'B' => 'Roman Empire', 'C' => 'British Empire', 'D' => 'Mongol Empire'], 'ans' => 'B'],
            ],

            'Mixed' => [
                ['q' => 'What is the smallest planet in our solar system?', 'opts' => ['A' => 'Mars', 'B' => 'Mercury', 'C' => 'Venus', 'D' => 'Pluto'], 'ans' => 'B'],
                ['q' => 'Which musical instrument has 88 keys?', 'opts' => ['A' => 'Violin', 'B' => 'Guitar', 'C' => 'Piano', 'D' => 'Harp'], 'ans' => 'C'],
                ['q' => 'What is the fastest land animal in the world?', 'opts' => ['A' => 'Lion', 'B' => 'Cheetah', 'C' => 'Leopard', 'D' => 'Gazelle'], 'ans' => 'B'],
            ],
        ];

        // সব প্রশ্নের একটি একত্রিক লিস্ট (ফলব্যাক বা অজানা ক্যাটাগরির জন্য)
        $fallbackBank = array_merge(...array_values($categorizedBank));

        // ৪. প্রতিটি ক্যাটাগরির জন্য ১০০০টি করে প্রশ্ন ইনসার্ট করা
        foreach ($categories as $categoryName => $category) {
            $bank = $categorizedBank[$categoryName] ?? $fallbackBank;
            $totalBankCount = count($bank);
            $data = [];

            for ($i = 1; $i <= 1000; $i++) {
                $item = $bank[($i - 1) % $totalBankCount];
                $setNumber = floor(($i - 1) / $totalBankCount) + 1;
                $questionText = $setNumber > 1 ? $item['q'] . " (Set {$setNumber})" : $item['q'];

                $data[] = [
                    'category_id' => $category->id,
                    'question_text' => $questionText,
                    'options' => json_encode($item['opts']),
                    'correct_option' => $item['ans'],
                    'base_point' => 100,
                    'time_limit_sec' => 15,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // ২০০ টি করে বাল্ক ইনসার্ট
                if (count($data) === 200) {
                    Question::insert($data);
                    $data = [];
                }
            }

            if (! empty($data)) {
                Question::insert($data);
            }
        }
    }
}