<?php

namespace App\Modules\Cart;

use App\Models\Product;
use App\Models\Coupon;

use App\Models\Cart;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Cart\Services\CartService;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

 public function showCart(Request $request)
{
    $user = $request->user();
    if (!$user) {
        Log::error('Unauthenticated user attempt');
        return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
    }

    $cartItems = $this->cartService->getCart($user->id);

    $data = [];
    $totalPrice = 0;

    foreach ($cartItems as $item) {
        $product = $item->product;

        if (!$product) {
            continue;
        }

        $originalPrice = $product->price;
        $discountedPrice = $originalPrice;
        $discountPercentage = 0;

        if (isset($product->saleable) && $product->saleable->is_active) {
            if ($product->saleable_type === 'App\\Models\\Discount') {
                $discount = $product->saleable;
                $discountPercentage = $discount->precentage;
                $discountedPrice = $originalPrice - ($originalPrice * $discountPercentage / 100);
            } elseif ($product->saleable_type === 'App\\Models\\FlashSale') {
                $discount = $product->saleable;
                $discountedPrice = $originalPrice - $discount->discount_value;
                $discountPercentage = round(($discount->discount_value / $originalPrice) * 100, 2);
            }
        }

        $quantity = $item->product_quantity;
        $lineTotal = $discountedPrice * $quantity;
        $totalPrice += $lineTotal;

        $data[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'original_price' => $originalPrice,
            'discounted_price' => round($discountedPrice, 2),
            'discount_percentage' => $discountPercentage,
            'quantity' => $quantity,
            'main_image' => $product->image ? asset($product->image) : null,
            'line_total' => round($lineTotal, 2), // لو حابة ترجعي السعر لكل منتج * الكمية
        ];
    }

    return response()->json([
        'success' => true,
        'data' => $data,
        'total_price' => round($totalPrice, 2),
    ]);
}



 public function addToCart(Request $request)
{
    $product = Product::find($request->product_id);
    if (!$product) {
        return response()->json([
            'success' => false,
            'message' => 'المنتج غير موجود',
        ], 404);
    }

    $user = $request->user();
    if (!$user) {
        Log::error('Unauthenticated user attempt');
        return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
    }

    $originalPrice = $product->price;
    $discountedPrice = $originalPrice;
    $discountPercentage = 0;

    // حساب الخصم
    if (isset($product->saleable) && $product->saleable->is_active) {
        if ($product->saleable_type === 'App\\Models\\Discount') {
            $discount = $product->saleable;
            $discountPercentage = $discount->precentage;
            $discountedPrice = $originalPrice - ($originalPrice * $discountPercentage / 100);
        } elseif ($product->saleable_type === 'App\\Models\\FlashSale') {
            $discount = $product->saleable;
            $discountedPrice = $originalPrice - $discount->discount_value;
            $discountPercentage = round(($discount->discount_value / $originalPrice) * 100, 2);
        }
    }

    // إضافة للسلة
    $this->cartService->addToCart([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_quantity' => $request->quantity,
        'price_at_time' => round($discountedPrice, 2),
    ]);

    // بيانات المنتج في الريسبونس
    $data = [
        'product_id' => $product->id,
        'name' => $product->name,
        'original_price' => $originalPrice,
        'discounted_price' => round($discountedPrice, 2),
        'discount_percentage' => $discountPercentage,
        'quantity' => $request->quantity,
        'main_image' => $product->image ? asset($product->image) : null,
    ];

    return response()->json([
        'success' => true,
        'message' => 'تمت الإضافة إلى السلة بالسعر بعد الخصم',
        'data' => $data
    ]);
}

    public function removeItem($id)
    { 
        $this->cartService->removeFromCart($id);

        return response()->json([
            'success' => true,
            'message' => 'تم الحذف من السلة'
        ]);
    }
public function updateQuantity(Request $request)
{
    $request->validate([
        'product_id' => 'required|exists:products,id',
        'operation' => 'required|in:increment,decrement', // أو 'plus', 'minus'
    ]);

    $user = $request->user();

    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
    }

    $cartItem = Cart::where('user_id', $user->id)
        ->where('product_id', $request->product_id)
        ->first();

    if (!$cartItem) {
        return response()->json(['success' => false, 'message' => 'العنصر غير موجود في السلة'], 404);
    }

    if ($request->operation === 'increment') {
        $cartItem->product_quantity += 1;
    } elseif ($request->operation === 'decrement') {
        $cartItem->product_quantity = max(1, $cartItem->product_quantity - 1);
    }

    $cartItem->save();

    return response()->json([
        'success' => true,
        'message' => 'تم تحديث الكمية بنجاح',
        'data' => $cartItem,
    ]);
}

    public function applyCoupon(Request $request)
    {
        // Validate the request first
        $request->validate([
            'coupon' => 'required|string'
        ]);
    
        // Find active coupon
        $coupon = Coupon::where('code', $request->coupon)
                       ->where('is_active', true)
                       ->first();
    
        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'كود الخصم غير صالح'
            ], 400);
        }
    
        // Check if user is authenticated
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
    
        // Check coupon validity dates
        $now = now();
        if ($coupon->start_at && $now->lt($coupon->start_at)) {
            return response()->json([
                'success' => false,
                'message' => 'كود الخصم غير فعال بعد'
            ], 400);
        }
    
        if ($coupon->end_at && $now->gt($coupon->end_at)) {
            return response()->json([
                'success' => false,
                'message' => 'كود الخصم انتهت صلايته'
            ], 400);
        }
    
        // Check usage limits
        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json([
                'success' => false,
                'message' => 'تم استنفا عدد استخدامات كود الخصم'
            ], 400);
        }
    
        // Check if user already used this coupon (if once_per_user is true)
        if ($coupon->once_per_user && $coupon->users()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لقد استخدمت هذا الكود من قبل'
            ], 400);
        }
    
        // Get cart items and calculate total
        $cartItems = $this->cartService->getCart($user->id);
        $total = $cartItems->sum('total_price');
    
        // Calculate discount - NOTE: Your Coupon model uses 'discount_value' not 'value'
        $discount = $coupon->discount_value; // Assuming fixed amount
        // If you want to support percentage, you'll need to add a 'type' field to your Coupon model
    
        // Apply discount
        $discountedTotal = max(0, $total - $discount); // Ensure total doesn't go negative
    
        // Record coupon usage
        $coupon->increment('used_count');
        $coupon->users()->attach($user->id);
    
        return response()->json([
            'success' => true,
            'message' => 'تم طبيق كود الخصم',
            'discount' => round($discount, 2),
            'total_after_discount' => round($discountedTotal, 2),
            'original_total' => round($total, 2)
        ]);
    }
}
