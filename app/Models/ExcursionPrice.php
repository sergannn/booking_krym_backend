<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcursionPrice extends Model
{
    protected $fillable = [
        'excursion_id',
        'passenger_type',
        'price',
        'price_without_entry',
        'price_with_entry',
        'seller_commission_percent',
        'partner_commission_percent',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_without_entry' => 'decimal:2',
        'price_with_entry' => 'decimal:2',
        'seller_commission_percent' => 'decimal:2',
        'partner_commission_percent' => 'decimal:2',
    ];

    public function excursion()
    {
        return $this->belongsTo(Excursion::class);
    }
    
    /**
     * Accessor для получения цены без входа
     * Если в excursion_prices NULL, берем из excursions
     */
    public function getPriceWithoutEntryAttribute($value)
    {
        // Если значение есть в excursion_prices и не пустое, используем его
        if ($value !== null && $value !== '') {
            return $value;
        }
        
        // Иначе берем из excursions через прямой SQL запрос
        if ($this->excursion_id) {
            $field = "price_{$this->passenger_type}_without_entry";
            $excursionValue = \Illuminate\Support\Facades\DB::table('excursions')
                ->where('id', $this->excursion_id)
                ->value($field);
            if ($excursionValue !== null && $excursionValue !== '') {
                return $excursionValue;
            }
        }
        
        return $value; // Возвращаем исходное значение (может быть null)
    }
    
    /**
     * Accessor для получения цены со входом
     * Если в excursion_prices NULL, берем из excursions
     */
    public function getPriceWithEntryAttribute($value)
    {
        // Если значение есть в excursion_prices и не пустое, используем его
        if ($value !== null && $value !== '') {
            return $value;
        }
        
        // Иначе берем из excursions через прямой SQL запрос
        if ($this->excursion_id) {
            $field = "price_{$this->passenger_type}_with_entry";
            $excursionValue = \Illuminate\Support\Facades\DB::table('excursions')
                ->where('id', $this->excursion_id)
                ->value($field);
            if ($excursionValue !== null && $excursionValue !== '') {
                return $excursionValue;
            }
        }
        
        return $value; // Возвращаем исходное значение (может быть null)
    }
    
    /**
     * Accessor для получения комиссии продавца
     * Если в excursion_prices NULL, берем из excursions
     */
    public function getSellerCommissionPercentAttribute($value)
    {
        // Если значение есть в excursion_prices и не пустое, используем его
        if ($value !== null && $value !== '') {
            return $value;
        }
        
        // Иначе берем из excursions через прямой SQL запрос
        if ($this->excursion_id) {
            $field = "price_{$this->passenger_type}_seller_commission";
            $excursionValue = \Illuminate\Support\Facades\DB::table('excursions')
                ->where('id', $this->excursion_id)
                ->value($field);
            if ($excursionValue !== null && $excursionValue !== '') {
                return $excursionValue;
            }
        }
        
        return $value ?: 10; // Значение по умолчанию
    }
    
    /**
     * Accessor для получения комиссии партнера
     * Если в excursion_prices NULL, берем из excursions
     */
    public function getPartnerCommissionPercentAttribute($value)
    {
        // Если значение есть в excursion_prices и не пустое, используем его
        if ($value !== null && $value !== '') {
            return $value;
        }
        
        // Иначе берем из excursions через прямой SQL запрос
        if ($this->excursion_id) {
            $field = "price_{$this->passenger_type}_partner_commission";
            $excursionValue = \Illuminate\Support\Facades\DB::table('excursions')
                ->where('id', $this->excursion_id)
                ->value($field);
            if ($excursionValue !== null && $excursionValue !== '') {
                return $excursionValue;
            }
        }
        
        return $value ?: 10; // Значение по умолчанию
    }
}
