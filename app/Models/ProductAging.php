<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAging extends Model
{
    use HasFactory;

    public $timestamps = false;
    
    protected $table = 'product_aging';

    protected $fillable = [
        'product_id',
        'variant_id',
        'branch_id',
        'last_sale_date',
        'days_without_sale',
        'quantity_on_hand',
        'aging_category',
        'updated_at',
    ];

    protected $casts = [
        'last_sale_date' => 'date',
        'updated_at' => 'datetime',
    ];

    // Auto-calculate days without sale and aging category
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($aging) {
            if ($aging->last_sale_date) {
                $aging->days_without_sale = now()->diffInDays($aging->last_sale_date);
                
                // Determine aging category
                if ($aging->days_without_sale <= 30) {
                    $aging->aging_category = '0-30 days';
                } elseif ($aging->days_without_sale <= 60) {
                    $aging->aging_category = '31-60 days';
                } elseif ($aging->days_without_sale <= 90) {
                    $aging->aging_category = '61-90 days';
                } else {
                    $aging->aging_category = '90+ days';
                }
            }
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
        return $this->belongsTo(Branch::class);
    }
}