<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'General Knowledge',
                'description' => 'Questions related to general knowledge and world affairs.',
            ],
            [
                'name' => 'Sports',
                'description' => 'Questions covering various sports, events, and athletes.',
            ],
            [
                'name' => 'Science',
                'description' => 'Questions about physics, chemistry, biology, and space.',
            ],
            [
                'name' => 'Movies',
                'description' => 'Trivia and questions about cinema, actors, and films.',
            ],
            [
                'name' => 'Gaming',
                'description' => 'Questions about video games, esports, and gaming history.',
            ],
            [
                'name' => 'Technology',
                'description' => 'Questions covering software, hardware, and tech innovations.',
            ],
            [
                'name' => 'History',
                'description' => 'Questions regarding historical events, eras, and figures.',
            ],
            [
                'name' => 'Mixed',
                'description' => 'A combination of questions from all categories.',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name'        => $category['name'],
                    'description' => $category['description'],
                    'is_active'   => true,
                ]
            );
        }
    }
}