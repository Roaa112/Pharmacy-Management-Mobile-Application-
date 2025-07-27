<?php
namespace App\Modules\Orders;

use Carbon\Carbon;

use App\Models\ProductSize;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\{Order, OrderItem, Cart, Address, DeliveryPrice, Setting, DiscountRule};


class OrderController extends Controller
{
 public function checkout(Request $request)
{
    $request->validate([
        'address_id' => 'required|exists:addresses,id',
        'payment_method' => 'required|in:cash,zaincash',
        'payment_image' => 'nullable|image|required_if:payment_method,zaincash'
    ]);

    $user = auth()->user();
    $address = Address::findOrFail($request->address_id);

    $deliveryPrice = DeliveryPrice::where('governorate', 'like', '%' . $address->city . '%')->first();

    if (!$deliveryPrice) {
        return response()->json(['message' => 'المدينة غير معرفة في جدول مصاريف التوصيل'], 422);
    }

    $cartItems = Cart::with('product')->where('user_id', $user->id)->get();
    if ($cartItems->isEmpty()) {
        return response()->json(['message' => 'عربة التسوق فارغة'], 422);
    }

    $subtotal = $cartItems->sum('total_price');
    $total = $subtotal + $deliveryPrice->price;

    $giftDescription = null;
    $giftRuleId = null;
   $now = Carbon::now()->addHours(3);




    $giftRules = DiscountRule::with('targets')
        ->whereIn('discount_type', ['buy_x_get_y', 'amount_gift'])
        ->where('starts_at', '<=', $now)
        ->where('ends_at', '>=', $now)
        ->get();

    foreach ($giftRules as $rule) {
        $buyTargets = $rule->targets->where('is_gift', false);
        $giftTarget = $rule->targets->where('is_gift', true)->first();

        if ($rule->discount_type === 'buy_x_get_y') {
            $totalQty = 0;

            foreach ($buyTargets as $target) {
                $matchedItems = $cartItems->filter(function ($item) use ($target) {
                    return match ($target->target_type) {
                        'product' => $item->product_id == $target->target_id,
                        'brand' => $item->product->brand_id == $target->target_id,
                        'category' => $item->product->category_id == $target->target_id,
                        default => false,
                    };
                });

                $totalQty += $matchedItems->sum('product_quantity');
            }

            if ($totalQty >= $rule->min_quantity) {
                $giftDescription = "هدية عند شراء {$rule->min_quantity} من العروض المحددة → هدية: " .
                    ucfirst($giftTarget->target_type) . " ID: {$giftTarget->target_id}";
                $giftRuleId = $rule->id;
                break;
            }
        }

        if ($rule->discount_type === 'amount_gift') {
            $totalValue = 0;

            foreach ($buyTargets as $target) {
                $matchedItems = $cartItems->filter(function ($item) use ($target) {
                    return match ($target->target_type) {
                        'product' => $item->product_id == $target->target_id,
                        'brand' => $item->product->brand_id == $target->target_id,
                        'category' => $item->product->category_id == $target->target_id,
                        default => false,
                    };
                });

                $totalValue += $matchedItems->sum('total_price');
            }

            if ($totalValue >= $rule->min_amount) {
                $giftDescription = "هدية عند الشراء بقيمة {$rule->min_amount} من العروض المحددة → هدية: " .
                    ucfirst($giftTarget->target_type) . " ID: {$giftTarget->target_id}";
                $giftRuleId = $rule->id;
                break;
            }
        }
    }

    // نقاط الولاء
    $settings = Setting::first();
    $spendX = (float) $settings->spend_x ?? 0;
    $getY = (int) $settings->get_y ?? 0;
    $pointsEarned = $spendX > 0 ? floor($subtotal / $spendX) * $getY : 0;

    // إنشاء الطلب
    $order = Order::create([
        'user_id' => $user->id,
        'address_id' => $address->id,
        'payment_method' => $request->payment_method,
        'payment_image' => $request->file('payment_image')?->store('payments', 'public'),
        'delivery_fee' => $deliveryPrice->price,
        'total_price' => $total,
        'gift_description' => $giftDescription,
        'gift_rule_id' => $giftRuleId,
        'points_earned' => $pointsEarned,
    ]);

    foreach ($cartItems as $item) {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $item->product_id,
            'product_size_id' => $item->product_size_id,
            'quantity' => $item->product_quantity,
            'price_at_time' => $item->price_at_time,
            'original_price' => $item->original_price,
            'total_price' => $item->total_price,
        ]);

        $productSize = ProductSize::find($item->product_size_id);
        if ($productSize) {
            $productSize->decrement('stock', $item->product_quantity);
        }
    }

    Cart::where('user_id', $user->id)->delete();

    return response()->json([
        'message' => 'تم إنشاء الطلب بنجاح',
        'order_id' => $order->id,
        'total_price' => $order->total_price,
        'points_earned' => $pointsEarned,
        'gift_description' => $giftDescription,
    ], 201);
}

public function updateStatus(Request $request, Order $order)
{
    $request->validate([
        'status' => 'required|in:pending,confirmed,canceled,completed',
    ]);

    $order->status = $request->status;
    $order->save();

    return redirect()->back()->with('success', 'تم تحديث حالة الطلب بنجاح');
}


public function index()
{
    $orders = Order::with(['user', 'address', 'items.product', 'items.size', 'giftRule'])
        ->latest()
        ->paginate(15);

    $totalEarned = Order::where('status', 'completed')->sum('total_price');

    return view('dashboard.orders.index', compact('orders', 'totalEarned'));
}




 public function track($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        $statusMeaning = $this->getStatusMeaning($order->status);

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'status' => $order->status,
            'status_meaning' => $statusMeaning,
            'last_update' => $order->updated_at->format('Y-m-d H:i'),
        ]);
    }

    private function getStatusMeaning($status)
    {
        return match ($status) {
            'pending' => 'قيد المراجعة من الإدارة',
            'confirmed' => 'تم تأكيد الطلب وجاري التحضير',
            'canceled' => 'تم إلغاء الطلب',
            'completed' => 'تم توصيل الطلب بنجاح',
            default => 'غير معروف'
        };
    }




  public function details($id)
{
    $order = \App\Models\Order::with(['user', 'address', 'items.product.productImages', 'items.size'])->find($id);

    if (!$order) {
        return response()->json([
            'success' => false,
            'message' => 'الطلب غير موجود'
        ], 404);
    }

    $statusMeaning = $this->getStatusMeaning($order->status);

    $products = $order->items->map(function ($item) {
        return [
            'name' => $item->product->name ?? 'منتج محذوف',
            'image' => $item->product->productImages->first()?->image_url ?? null,
            'quantity' => $item->quantity,
            'original_price' => $item->original_price,
            'price_at_time' => $item->price_at_time,
            'total_price' => $item->total_price,
            'size' => $item->size->size ?? 'غير متوفر',
        ];
    });

    return response()->json([
        'success' => true,
        'order_id' => $order->id,
        'status' => $order->status,
        'status_meaning' => $statusMeaning,
        'payment_method' => $order->payment_method,
        'delivery_fee' => $order->delivery_fee,
        'total_price' => $order->total_price,
        'points_earned' => $order->points_earned,
        'gift_description' => $order->gift_description,
        'address' => [
            'city' => $order->address->city ?? '-',
            'street' => $order->address->street ?? '-',
            'building' => $order->address->building ?? '-',
            'apartment' => $order->address->apartment ?? '-',
            'landmark' => $order->address->landmark ?? '-',
        ],
        'products' => $products,
        'created_at' => $order->created_at->format('Y-m-d H:i'),
        'last_update' => $order->updated_at->format('Y-m-d H:i'),
    ]);
}


public function groupedByStatus(Request $request)
{
    $user = auth()->user(); // أو auth('sanctum')->user() لو API تستخدم Sanctum

    $orders = \App\Models\Order::withCount('items')
        ->where('user_id', $user->id)
        ->select('id', 'status', 'total_price', 'created_at')
        ->orderByDesc('created_at')
        ->get();

    $grouped = $orders->groupBy('status')->map(function ($orders) {
        return $orders->map(function ($order) {
            return [
                'order_id'     => $order->id,
                'status'       => $order->status,
                'total_price'  => $order->total_price,
                'items_count'  => $order->items_count,
                'date'         => $order->created_at->format('Y-m-d H:i'),
            ];
        });
    });

    return response()->json([
        'success' => true,
        'data' => $grouped
    ]);
}

}
