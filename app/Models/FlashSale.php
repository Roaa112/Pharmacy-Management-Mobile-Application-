<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashSale extends Model
{
    protected $fillable = [
        'discount_value',
        'is_active',
        'date',
        'time',
    ];
}

