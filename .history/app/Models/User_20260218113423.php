<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'phone',
        'role_id',
        'branch_id',
        'is_active',
        'last_login',
         // HR Fields
        'national_id',
        'date_of_birth',
        'gender',
        'marital_status',
        'joining_date',
        'job_title',
        'department',
        'basic_salary',
        'transportation_allowance',
        'housing_allowance',
        'communication_allowance',
        'meal_allowance',
        'accommodation_allowance',
        'emergency_contact_name',
        'emergency_contact_phone',
        'address',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

protected $casts = [
    'email_verified_at' => 'datetime',
    'last_login' => 'datetime',
    'is_active' => 'boolean',
    'password' => 'hashed',
    'date_of_birth' => 'date',
    'joining_date' => 'date',
    'basic_salary' => 'decimal:3',
    'transportation_allowance' => 'decimal:3',
    'housing_allowance' => 'decimal:3',
    'communication_allowance' => 'decimal:3',
    'meal_allowance' => 'decimal:3',
    'accommodation_allowance' => 'decimal:3',
];

    // Relationships
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }

    // Helper method to check permission
    public function hasPermission($permissionName)
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->permissions()
            ->where('permission_name', $permissionName)
            ->exists();
    }

    // Helper method to check multiple permissions
    public function hasAnyPermission(array $permissions)
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->permissions()
            ->whereIn('permission_name', $permissions)
            ->exists();
    }

    // Helper method to check if user has all permissions
    public function hasAllPermissions(array $permissions)
    {
        if (!$this->role) {
            return false;
        }

        $userPermissions = $this->role->permissions()
            ->pluck('permission_name')
            ->toArray();

        return empty(array_diff($permissions, $userPermissions));
    }

    // Check if user is super admin
    public function isSuperAdmin()
    {
        return $this->role && $this->role->role_name === 'Super Admin';
    }


    // HR Relationships
    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function bonuses()
    {
        return $this->hasMany(Bonus::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    // Helper: Get total salary including allowances
    public function getTotalSalaryAttribute()
    {
        return $this->basic_salary 
            + $this->transportation_allowance 
            + $this->housing_allowance 
            + $this->communication_allowance 
            + $this->meal_allowance 
            + $this->accommodation_allowance;
    }
}