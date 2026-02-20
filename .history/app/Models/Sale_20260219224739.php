<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_number',
        'branch_id',
        'cash_register_id',
        'cashier_id',
        'sales_staff_id', // ✅ NEW
        'customer_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'coupon_discount', // ✅ NEW
        'coupon_code', // ✅ NEW
        'payment_method',
        'cash_received',
        'change_given',
        'card_amount',
        'card_reference',
        'is_gift', // ✅ NEW
        'is_employee_purchase', // ✅ NEW
        'employee_discount_amount', // ✅ NEW
        'status',
        'notes',
        'sale_date',
    ];

    protected $casts = [
        'subtotal' => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'tax_amount' => 'decimal:3',
        'total_amount' => 'decimal:3',
        'coupon_discount' => 'decimal:3',
        'cash_received' => 'decimal:3',
        'change_given' => 'decimal:3',
        'card_amount' => 'decimal:3',
        'employee_discount_amount' => 'decimal:3',
        'is_gift' => 'boolean',
        'is_employee_purchase' => 'boolean',
        'sale_date' => 'datetime',
    ];

    // Auto-generate sale number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (!$sale->sale_number) {
                $sale->sale_number = 'SALE-' . date('Ymd') . '-' . str_pad(
                    Sale::whereDate('created_at', today())->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );
            }

            if (!$sale->sale_date) {
                $sale->sale_date = now();
            }
        });
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function salesStaff() // ✅ NEW
    {
        return $this->belongsTo(User::class, 'sales_staff_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function returns()
    {
        return $this->hasMany(ReturnModel::class);
    }

    public function couponUsage() // ✅ NEW
    {
        return $this->hasOne(CouponUsage::class);
    }

    public function discountAuthorizations() // ✅ NEW
    {
        return $this->hasMany(DiscountAuthorizationLog::class);
    }
}