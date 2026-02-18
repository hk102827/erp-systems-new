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
}