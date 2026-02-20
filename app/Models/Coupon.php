<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'coupon_code',
        'coupon_name',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_purchase_amount',
        'usage_limit',
        'usage_limit_per_user',
        'times_used',
        'valid_from',
        'valid_until',
        'applicable_branches',
        'applicable_products',
        'applicable_categories',
        'is_active',
        'channel',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:3',
        'max_discount_amount' => 'decimal:3',
        'min_purchase_amount' => 'decimal:3',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'applicable_branches' => 'array',
        'applicable_products' => 'array',
        'applicable_categories' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usage()
    {
        return $this->hasMany(CouponUsage::class);
    }

    // Helper: Check if coupon is valid
    public function isValid($branchId = null, $totalAmount = 0)
    {
        // Check if active
        if (!$this->is_active) {
            return ['valid' => false, 'message' => 'Coupon is not active'];
        }

        // Check date validity
        $now = now()->toDateString();
        if ($now < $this->valid_from || $now > $this->valid_until) {
            return ['valid' => false, 'message' => 'Coupon has expired or not yet valid'];
        }

        // Check usage limit
        if ($this->usage_limit && $this->times_used >= $this->usage_limit) {
            return ['valid' => false, 'message' => 'Coupon usage limit reached'];
        }

        // Check minimum purchase amount
        if ($totalAmount < $this->min_purchase_amount) {
            return ['valid' => false, 'message' => "Minimum purchase amount is {$this->min_purchase_amount} KD"];
        }

        // Check branch applicability
        if ($branchId && $this->applicable_branches && !in_array($branchId, $this->applicable_branches)) {
            return ['valid' => false, 'message' => 'Coupon not applicable for this branch'];
        }

        return ['valid' => true, 'message' => 'Coupon is valid'];
    }

    // Helper: Calculate discount
    public function calculateDiscount($totalAmount)
    {
        if ($this->discount_type === 'Percentage') {
            $discount = ($totalAmount * $this->discount_value) / 100;
            
            // Apply max discount if set
            if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
                $discount = $this->max_discount_amount;
            }
            
            return $discount;
        } else {
            // Fixed amount
            return min($this->discount_value, $totalAmount);
        }
    }
}