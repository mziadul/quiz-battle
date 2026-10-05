<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'question_text',
        'options',
        'correct_option',
        'base_point',
        'time_limit_sec',
    ];

    protected $casts = [
        'options' => 'array',
    ];
}