<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'leave_type_name',
        'max_days_per_year',
        'is_paid',
        'requires_approval',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}