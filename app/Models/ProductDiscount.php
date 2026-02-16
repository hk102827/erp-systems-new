<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductDiscount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'variant_id',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper: Check if discount is currently active
    public function isCurrentlyActive()
    {
        return $this->is_active 
            && $this->start_date <= now() 
            && $this->end_date >= now();
    }

    // Helper: Calculate discounted price
    public function calculateDiscountedPrice($originalPrice)
    {
        if ($this->discount_type === 'Percentage') {
            return $originalPrice - ($originalPrice * $this->discount_value / 100);
        } else {
            return $originalPrice - $this->discount_value;
        }
    }
}