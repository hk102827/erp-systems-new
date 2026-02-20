<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_register_id',
        'branch_id',
        'user_id',
        'shift_number',
        'shift_start',
        'shift_end',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'cash_difference',
        'total_transactions',
        'total_sales',
        'total_discounts',
        'total_returns',
        'net_sales',
        'cash_sales',
        'card_sales',
        'knet_sales',
        'mobile_payment_sales',
        'cash_in',
        'cash_out',
        'notes',
        'status',
    ];

    protected $casts = [
        'shift_start' => 'datetime',
        'shift_end' => 'datetime',
        'opening_cash' => 'decimal:3',
        'closing_cash' => 'decimal:3',
        'expected_cash' => 'decimal:3',
        'cash_difference' => 'decimal:3',
        'total_sales' => 'decimal:3',
        'total_discounts' => 'decimal:3',
        'total_returns' => 'decimal:3',
        'net_sales' => 'decimal:3',
        'cash_sales' => 'decimal:3',
        'card_sales' => 'decimal:3',
        'knet_sales' => 'decimal:3',
        'mobile_payment_sales' => 'decimal:3',
        'cash_in' => 'decimal:3',
        'cash_out' => 'decimal:3',
    ];

    // Auto-generate shift number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            if (!$report->shift_number) {
                $report->shift_number = 'SHIFT-' . date('Ymd') . '-' . str_pad(
                    ShiftReport::whereDate('created_at', today())->count() + 1,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
    }

    // Relationships
    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}