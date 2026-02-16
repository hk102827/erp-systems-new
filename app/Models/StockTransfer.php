<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_number',
        'from_branch_id',
        'to_branch_id',
        'transfer_type',
        'status',
        'requested_by',
        'approved_by',
        'transfer_date',
        'received_date',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'received_date' => 'date',
    ];

    // Auto-generate transfer number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            if (!$transfer->transfer_number) {
                $transfer->transfer_number = static::generateUniqueTransferNumber();
            }
        });
    }


     private static function generateUniqueTransferNumber()
    {
        $date = date('Ymd');
        $prefix = "TRF{$date}";
        
        $lastTransfer = static::where('transfer_number', 'LIKE', "{$prefix}%")
            ->orderBy('transfer_number', 'desc')
            ->lockForUpdate()
            ->first();
        
        if ($lastTransfer) {
            $lastNumber = (int) substr($lastTransfer->transfer_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    // Relationships
    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class, 'transfer_id');
    }
}