<?php
namespace App\Modules\Favorite;

use App\Models\Product;
use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function toggle(Request $request, $productId)
    {

         
        $user = $request->user();
    
        if (!$user) {
            Log::error('Unauthenticated user attempt');
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }
    
     
        $favorite = Favorite::where('user_id', $user->id)
                            ->where('product_id', $productId)
                            ->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json(['message' => 'Removed from favorites']);
        } else {
            Favorite::create([
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);
            return response()->json(['message' => 'Added to favorites']);
        }
    }



public function list()
{
    $user = Auth::user();

    $favorites = $user->favorites()->with(['product.category', 'product.productImages', 'product.saleable'])->get();

    $data = $favorites->map(function ($favorite) {
        $product = $favorite->product;

        $originalPrice = (float) $product->price;
        $discountedPrice = $originalPrice;
        $discountPercentage = 0;

        if ($product->saleable && $product->saleable->is_active) {
            if ($product->saleable_type === 'App\\Models\\Discount') {
                $discountedPrice = $originalPrice - ($originalPrice * $product->saleable->precentage / 100);
                $discountPercentage = $product->saleable->precentage;
            } elseif ($product->saleable_type === 'App\\Models\\FlashSale') {
                $discountedPrice = $originalPrice - $product->saleable->discount_value;
                $discountPercentage = ($product->saleable->discount_value / $originalPrice) * 100;
            }
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'main_image_url' => $product->image ? asset( $product->image) : null,
            'images' => $product->productImages->map(fn($img) => asset( $img->image_path)),
            'price_before_discount' => round($originalPrice, 2),
            'price_after_discount' => round($discountedPrice, 2),
            'discount_percentage' => round($discountPercentage, 2),
            'category' => $product->category->name ?? null,
            'rate' => $product->rate,
            'quantity' => $product->quantity,
            'is_favorite' => true,
          'ratings_count' => $product->ratings_count,
                'average_rating' => $product->average_rating,
        ];
    });

    return response()->json([
        'status' => 'success',
        'message' => 'Favorites.success.list',
        'data' => $data,
        'count' => $data->count(),
    ]);
}


}
