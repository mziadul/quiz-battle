<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'question',
        'options',
        'correct_answer',
        'difficulty',
        'language',
        'status',
        'is_validated',
    ];

    protected $casts = [
        'options' => 'array', // JSON options automatic array-te cast hobe
        'is_validated' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}