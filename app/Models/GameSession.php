<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'player1_id',
        'player2_id',
        'player1_score', // 👈 যুক্ত করুন
        'player2_score', // 👈 যুক্ত করুন
        'status',
        'winner_id',
        'started_at',
        'ended_at',
    ];

    // Category Relationship
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Player 1 Relationship
    public function player1()
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    // Player 2 Relationship
    public function player2()
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    // Winner Relationship
    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}