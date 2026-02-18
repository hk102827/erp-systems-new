<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'payroll_month',
        'basic_salary',
        'total_allowances',
        'total_bonuses',
        'total_deductions',
        'net_salary',
        'working_days',
        'present_days',
        'absent_days',
        'leave_days',
        'status',
        'payment_date',
        'approved_by',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:3',
        'total_allowances' => 'decimal:3',
        'total_bonuses' => 'decimal:3',
        'total_deductions' => 'decimal:3',
        'net_salary' => 'decimal:3',
        'payment_date' => 'date',
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