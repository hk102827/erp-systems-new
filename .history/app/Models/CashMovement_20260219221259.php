<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_register_id',
        'type',
        'amount',
        'reason',
        'reference_id',
        'reference_type',
        'recorded_by',
        'movement_date',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'movement_date' => 'datetime',
    ];

    // Relationships
    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Polymorphic relationship for reference
    public function reference()
    {
        return $this->morphTo();
    }
}