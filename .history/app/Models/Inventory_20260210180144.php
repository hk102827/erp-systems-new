<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $fillable = [
        'product_id',
        'variant_id',
        'branch_id',
        'quantity',
        'reserved_quantity',
        'available_quantity',
        'reorder_point',
        'last_updated',
    ];

    protected $casts = [
        'last_updated' => 'datetime',
    ];

    // Auto-calculate available quantity
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($inventory) {
            $inventory->available_quantity = $inventory->quantity - $inventory->reserved_quantity;
        });
    }

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
    return $this->belongsTo(Branch::class, 'branch_id');
}


    // Helper: Check if stock is low
    public function isLowStock()
    {
        return $this->available_quantity <= $this->reorder_point;
    }
}