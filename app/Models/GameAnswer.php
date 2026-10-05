<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_session_id',
        'user_id',
        'question_id',
        'selected_option',
        'time_taken',
        'is_correct',
        'score',
    ];
}