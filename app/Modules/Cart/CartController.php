<?php

namespace App\Modules\Cart;

use App\Models\Cart;
use App\Models\Coupon;

use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Modules\Cart\Services\CartService;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    // public function showCart(Request $request)
    // {
    //     $user = $request->user();
    //     if (!$user) {
    //         Log::error('Unauthenticated user attempt');
    //         return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
    //     }

    //     $cartItems = $this->cartService->getCart($user->id);

    //     $data = [];
    //     $totalPrice = 0;

    //     foreach ($cartItems as $item) {
    //         $product = $item->product;

    //         if (!$product) {
    //             continue;
    //         }

    //         $originalPrice = $product->price;
    //         $discountedPrice = $originalPrice;
    //         $discountPercentage = 0;



    //         $quantity = $item->product_quantity;
    //         $lineTotal = $discountedPrice * $quantity;
    //         $totalPrice += $lineTotal;

    //         $data[] = [
    //             'product_id' => $product->id,
    //             'name' => $product->name,
    //             'original_price' => $originalPrice,
    //             'discounted_price' => round($discountedPrice, 2),
    //             'discount_percentage' => $discountPercentage,
    //             'quantity' => $quantity,
    //             'main_image' => $product->image ? asset($product->image) : null,
    //             'line_total' => round($lineTotal, 2), // لو حابة ترجعي السعر لكل منتج * الكمية
    //         ];
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'data' => $data,
    //         'total_price' => round($totalPrice, 2),
    //     ]);
    // }

public function showCart(Request $request)
{
    $user = $request->user();
    if (!$user) {
        \Log::error('Unauthenticated user attempt');
        return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
    }

    $cartItems = $this->cartService->getCart($user->id);

    $data = [];
    $totalPrice = 0;

    foreach ($cartItems as $item) {
        $product = $item->product;
        $size = $item->size;

        if (!$product || !$size) {
            continue;
        }

        $originalPrice = $size->price;

        // ✅ نجيب السعر الحالي بعد الخصم من available_sizes
        $currentPrice = collect($product->available_sizes)
            ->firstWhere('size', $size->size)['price'] ?? $originalPrice;

        // ✅ تحديث السطر في السلة لو السعر تغير
        if ($item->price_at_time != $currentPrice) {
            $item->price_at_time = $currentPrice;
            $item->original_price = $originalPrice;
            $item->total_price = $currentPrice * $item->product_quantity;
            $item->save();
        }

        $lineTotal = $item->price_at_time * $item->product_quantity;
        $totalPrice += $lineTotal;

        $discountPercentage = null;
        if ($originalPrice > 0) {
            $discountPercentage = round((($originalPrice - $currentPrice) / $originalPrice) * 100);
        }

        $data[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'original_price' => $originalPrice,
            'discounted_price' => round($currentPrice, 2),
            'discount_percentage' => $discountPercentage,
            'quantity' => $item->product_quantity,
            'main_image' => $product->image_url,
            'line_total' => round($lineTotal, 2),
            'size' => $size->size,
        ];
    }

    return response()->json([
        'success' => true,
        'data' => $data,
        'total_price' => round($totalPrice, 2),
    ]);
}




// public function addToCart(Request $request)
// {
//     $request->validate([
//         'product_id'   => 'required|exists:products,id',
//         'product_size' => 'required|exists:product_sizes,id',
//         'quantity'     => 'required|integer|min:1',
//     ]);

//     $product = Product::find($request->product_id);
//     $productSize = ProductSize::find($request->product_size);

//     $user = $request->user();
//     if (!$user) {
//         return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
//     }

//     $originalPrice = $productSize->price;
//     $discountedPrice = $originalPrice;

//     // خصم rules
//     $ruleDiscount = $product->getActiveDiscount();
//     if ($ruleDiscount) {
//         if ($ruleDiscount->discount_type === 'fixed') {
//             $discountedPrice = max(0, $originalPrice - $ruleDiscount->discount_value);
//         } elseif ($ruleDiscount->discount_type === 'percent') {
//             $discountedPrice = round($originalPrice * (1 - $ruleDiscount->discount_value / 100), 2);
//         }
//     }

//     // خصم flash sale
//     if (
//         $product->saleable_type === FlashSale::class &&
//         $product->saleable &&
//         $product->saleable->is_active
//     ) {
//         $start = Carbon::parse($product->saleable->date . ' ' . $product->saleable->time);
//         $end = $start->copy()->addHour();

//         if (now()->between($start, $end)) {
//             $flashDiscount = max(0, $originalPrice - $product->saleable->discount_value);
//             $discountedPrice = min($discountedPrice, $flashDiscount);
//         }
//     }

//     $totalPrice = $discountedPrice * $request->quantity;

//     $data = $this->cartService->addToCart([
//         'user_id'         => $user->id,
//         'product_id'      => $product->id,
//         'product_size_id' => $productSize->id, // ✅ استخدمي الاسم الصحيح
//         'product_quantity'=> $request->quantity,
//         'price_at_time'   => $discountedPrice,
//         'total_price'     => $totalPrice,
//         'original_price'  => $originalPrice,
//     ]);

//     return response()->json([
//         'success' => true,
//         'message' => 'تمت الإضافة إلى السلة بالسعر بعد الخصم',
//         'data' => $data
//     ]);
// }

public function addToCart(Request $request)
{
    $request->validate([
        'product_id'   => 'required|exists:products,id',
        'product_size' => 'required|exists:product_sizes,id',
        'quantity'     => 'required|integer|min:1',
    ]);

    $product = Product::with('sizes')->findOrFail($request->product_id);
    $productSize = ProductSize::findOrFail($request->product_size);

    $user = $request->user();
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
    }

    $originalPrice = $productSize->price;

    // ✅ نجيب final_price الحقيقي من available_sizes
    $finalPrice = collect($product->available_sizes)->firstWhere('size', $productSize->size)['price'] ?? $originalPrice;

    $totalPrice = $finalPrice * $request->quantity;

    $data = $this->cartService->addToCart([
        'user_id'          => $user->id,
        'product_id'       => $product->id,
        'product_size_id'  => $productSize->id,
        'product_quantity' => $request->quantity,
        'price_at_time'    => $finalPrice,
        'total_price'      => $totalPrice,
        'original_price'   => $originalPrice,
    ]);

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
        'operation' => 'nullable|in:increment,decrement',
        'old_size_id' => 'nullable|exists:product_sizes,id',
        'new_size_id' => 'nullable|exists:product_sizes,id',
    ]);

    $user = $request->user();

    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
    }

    $sizeId = $request->old_size_id ?? $request->new_size_id;

    $cartItem = Cart::where('user_id', $user->id)
        ->where('product_id', $request->product_id)
        ->when($sizeId, fn($query) => $query->where('product_size_id', $sizeId))
        ->first();

    if (!$cartItem) {
        return response()->json(['success' => false, 'message' => 'العنصر غير موجود في السلة'], 404);
    }

    $product = $cartItem->product;

    // 1. تعديل الكمية
    if ($request->filled('operation')) {
        if ($request->operation === 'increment') {
            $cartItem->product_quantity += 1;
        } elseif ($request->operation === 'decrement') {
            $cartItem->product_quantity = max(1, $cartItem->product_quantity - 1);
        }
    }

    // 2. تعديل الحجم (إن وجد)
    if ($request->filled('old_size_id') && $request->filled('new_size_id')) {
        $newSize = ProductSize::find($request->new_size_id);
        if (!$newSize) {
            return response()->json(['success' => false, 'message' => 'الحجم الجديد غير موجود'], 404);
        }

        $finalPrice = collect($product->available_sizes)
            ->firstWhere('size', $newSize->size)['price'] ?? $newSize->price;

        // لو فيه عنصر تاني بنفس الحجم الجديد → دمج
        $existingItem = Cart::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->where('product_size_id', $newSize->id)
            ->first();

        if ($existingItem) {
            $existingItem->product_quantity += $cartItem->product_quantity;
            $existingItem->price_at_time = $finalPrice;
            $existingItem->original_price = $newSize->price;
            $existingItem->total_price = $finalPrice * $existingItem->product_quantity;
            $existingItem->save();

            $cartItem->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الحجم ودمج الكمية بالسعر الجديد',
                'data' => $existingItem,
            ]);
        }

        // تغيير الحجم في العنصر الحالي
        $cartItem->product_size_id = $newSize->id;
        $cartItem->price_at_time = $finalPrice;
        $cartItem->original_price = $newSize->price;
        $cartItem->total_price = $finalPrice * $cartItem->product_quantity;
    } else {
        // ✅ مجرد تحديث السعر الكلي لو تم تعديل الكمية فقط بدون تغيير حجم
        $size = $cartItem->size;
        $finalPrice = collect($product->available_sizes)
            ->firstWhere('size', $size->size)['price'] ?? $size->price;

        $cartItem->price_at_time = $finalPrice;
        $cartItem->original_price = $size->price;
        $cartItem->total_price = $finalPrice * $cartItem->product_quantity;
    }

    $cartItem->save();

    return response()->json([
        'success' => true,
        'message' => 'تم تحديث العنصر بنجاح مع تعديل السعر',
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
