<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'returns';

    protected $fillable = [
        'return_number',
        'sale_id',
        'branch_id',
        'processed_by',
        'return_amount',
        'refund_method',
        'reason',
        'status',
        'return_date',
    ];

    protected $casts = [
        'return_amount' => 'decimal:3',
        'return_date' => 'datetime',
    ];

    // Auto-generate return number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($return) {
            if (!$return->return_number) {
                $return->return_number = 'RET-' . date('Ymd') . '-' . str_pad(
                    ReturnModel::whereDate('created_at', today())->count() + 1,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }

            if (!$return->return_date) {
                $return->return_date = now();
            }
        });
    }

    // Relationships
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items()
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}