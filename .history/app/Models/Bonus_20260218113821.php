<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bonus extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'bonus_type',
        'amount',
        'bonus_date',
        'description',
        'approved_by',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'bonus_date' => 'date',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}