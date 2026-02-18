<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'attendance_date',
        'check_in',
        'check_out',
        'total_hours',
        'status',
        'device_id',
        'notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in' => 'datetime:H:i',
        'check_out' => 'datetime:H:i',
    ];

    // Auto-calculate total hours
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($attendance) {
            if ($attendance->check_in && $attendance->check_out) {
                $checkIn = \Carbon\Carbon::parse($attendance->check_in);
                $checkOut = \Carbon\Carbon::parse($attendance->check_out);
                $attendance->total_hours = $checkIn->diffInMinutes($checkOut);
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Helper: Get formatted total hours
    public function getFormattedTotalHours()
    {
        $hours = floor($this->total_hours / 60);
        $minutes = $this->total_hours % 60;
        return sprintf('%02d:%02d', $hours, $minutes);
    }
}