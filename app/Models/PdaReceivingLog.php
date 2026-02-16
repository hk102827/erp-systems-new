<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdaReceivingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_id',
        'branch_id',
        'quantity_received',
        'purchase_reference',
        'received_by',
        'device_id',
        'received_date',
        'sync_status',
        'synced_at',
    ];

    protected $casts = [
        'received_date' => 'date',
        'synced_at' => 'datetime',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}