<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address_id',
        'payment_method',
        'payment_image',
        'delivery_fee',
        'total_price',
        'gift_description',
        'gift_rule_id',     
        'points_earned',
        'status'
    ];

    // علاقة الطلب بالمستخدم (العميل اللي عمل الطلب)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // علاقة الطلب بعنوان التوصيل
    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    // علاقة الطلب بعناصر الطلب (كل منتج تم طلبه)
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    // علاقة الطلب بقانون الخصم/الهدية (لو فيه هدية)
    public function giftRule()
    {
        return $this->belongsTo(DiscountRule::class, 'gift_rule_id');
    }
}
