<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'user_id',
        'register_number',
        'opening_balance',
        'closing_balance',
        'expected_balance',
        'difference',
        'status',
        'opened_at',
        'closed_at',
        'opening_notes',
        'closing_notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:3',
        'closing_balance' => 'decimal:3',
        'expected_balance' => 'decimal:3',
        'difference' => 'decimal:3',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // Auto-generate register number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($register) {
            if (!$register->register_number) {
                $register->register_number = 'REG-' . date('Ymd') . '-' . str_pad(
                    CashRegister::whereDate('created_at', today())->count() + 1,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function cashMovements()
    {
        return $this->hasMany(CashMovement::class);
    }

    // Helper: Calculate expected balance
    public function calculateExpectedBalance()
    {
        $sales = $this->sales()->where('payment_method', 'Cash')->sum('total_amount');
        $returns = $this->sales()->with('returns')->get()->flatMap->returns->sum('return_amount');
        $cashIns = $this->cashMovements()->where('type', 'Cash In')->sum('amount');
        $cashOuts = $this->cashMovements()->where('type', 'Cash Out')->sum('amount');

        return $this->opening_balance + $sales - $returns + $cashIns - $cashOuts;
    }
}