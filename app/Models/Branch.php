<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_name',
        'branch_type',
        'has_pos',
        'has_inventory',
        'has_cash_bank',
        'address',
        'phone',
        'email',
        'is_active',
        'is_temporary',
        'opening_date',
        'closing_date',
    ];

    protected $casts = [
        'has_pos' => 'boolean',
        'has_inventory' => 'boolean',
        'has_cash_bank' => 'boolean',
        'is_active' => 'boolean',
        'is_temporary' => 'boolean',
        'opening_date' => 'date',
        'closing_date' => 'date',
    ];

    // Relationships
    public function users()
    {
        return $this->hasMany(User::class);
    }
}