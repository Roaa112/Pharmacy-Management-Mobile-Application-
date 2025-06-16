<?php

namespace App\Modules\Product;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Product\Services\ProductService;
use App\Modules\Product\Repositories\ProductsRepository;
use App\Modules\Product\Requests\ListAllProductsRequest;


class ApiProductController extends Controller
{
    public function __construct(private ProductService $productService ,private ProductsRepository $productRepository)
    {
    }
  public function listAllProducts(ListAllProductsRequest $request)
{
    $products = $this->productService->listAllProducts($request->all());

    $products['data'] = collect($products['data'])->map(function ($product) {
        $originalPrice = (float) $product['price'];
        $discountedPrice = $originalPrice;
        $discountPercentage = 0;

        // Check if product has an active saleable relation
        if (!empty($product['saleable']) && $product['saleable']['is_active']) {
            if ($product['saleable_type'] === 'App\\Models\\Discount') {
                $discount = $product['saleable'];
                $discountedPrice = $originalPrice - ($originalPrice * $discount['precentage'] / 100);
                $discountPercentage = $discount['precentage'];
            } elseif ($product['saleable_type'] === 'App\\Models\\FlashSale') {
                $discount = $product['saleable'];
                $discountedPrice = $originalPrice - $discount['discount_value'];
                $discountPercentage = ($discount['discount_value'] / $originalPrice) * 100;
            }
        }

        $product['price_before_discount'] = round($originalPrice, 2);
        $product['price_after_discount'] = round($discountedPrice, 2);
        $product['discount_percentage'] = round($discountPercentage, 2);

        return $product;
    });

    return successJsonResponse(
        $products['data'],
        __('Products.success.get_all_brands'),
        $products['count']
    );
}

    public function listAllProductsOnSale(ListAllProductsRequest $request)
    {
        $products = $this->productService->listAllProducts($request->all());
    
        $products['data'] = collect($products['data'])
            ->filter(function ($product) {
                return isset($product['saleable']) && $product['saleable']['is_active'];
            })
            ->map(function ($product) {
                $originalPrice = (float) $product['price'];
                $discountedPrice = $originalPrice;
                $discountPercentage = 0;
    
                if (isset($product['saleable']) && $product['saleable']['is_active']) {
                    if ($product['saleable_type'] === 'App\\Models\\Discount') {
                        $discount = $product['saleable'];
                        $discountedPrice = $originalPrice - ($originalPrice * $discount['precentage'] / 100);
                        $discountPercentage = $discount['precentage'];
                    } elseif ($product['saleable_type'] === 'App\\Models\\FlashSale') {
                        $discount = $product['saleable'];
                        $discountedPrice = $originalPrice - $discount['discount_value'];
                        $discountPercentage = ($discount['discount_value'] / $originalPrice) * 100;
                    }
                }
    
                $product['price_before_discount'] = round($originalPrice, 2);
                $product['price_after_discount'] = round($discountedPrice, 2);
                $product['discount_percentage'] = round($discountPercentage, 2);
    
                return $product;
            })
            ->values(); 
    
        return successJsonResponse(
            $products['data'],
            __('Products.success.get_all_brands')
        );
    }
    
    
    public function showProduct($id)
    {
        // جلب المنتج باستخدام الـ ID
        $product = Product::with(['saleable', 'category'])->find($id);
    
        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], 404);
        }
    
        $originalPrice = (float) $product->price;
        $discountedPrice = $originalPrice;
        $discountPercentage = 0;
    
        if (isset($product->saleable) && $product->saleable->is_active) {
            if ($product->saleable_type === 'App\\Models\\Discount') {
                $discount = $product->saleable;
                $discountedPrice = $originalPrice - ($originalPrice * $discount->precentage / 100);
                $discountPercentage = $discount->precentage;
            } elseif ($product->saleable_type === 'App\\Models\\FlashSale') {
                $discount = $product->saleable;
                $discountedPrice = $originalPrice - $discount->discount_value;
                $discountPercentage = ($discount->discount_value / $originalPrice) * 100;
            }
        }
    
        // إضافة بيانات الخصم والتسعير للـ product
        $product->price_before_discount = round($originalPrice, 2);
        $product->price_after_discount = round($discountedPrice, 2);
        $product->discount_percentage = round($discountPercentage, 2);
        $product->category_name = $product->category->name ?? null;
        // إرجاع الـ product مع تفاصيل الخصم
        return response()->json([
            'data' => $product,
        ], 200);
    }
    
    public function searchProducts(Request $request)
    {
        $queryCriteria = $request->all();


        if (!isset($queryCriteria['search'])) {
            return response()->json([
                'message' => 'Search term is required.',
            ], 400);
        }

        // مناداة دالة البحث في الـ Service
        $products = $this->productService->searchProducts($queryCriteria);

        return response()->json([
            'data' => $products['data'],
            'count' => $products['count'],
        ]);
    }

    public function latestProducts()
    {
        $products = $this->productService->getLatestProducts();

        return successJsonResponse(
            $products['data'],
            __('Products.success.get_latest'),
            $products['count']
        );
    }
    public function topDiscountProducts()
    {
        $products = $this->productService->getTopDiscountProducts();
    
        return successJsonResponse(
            $products['data'],
            __('Products.success.get_top_discount'),
            $products['count']
        );
    }
   



    public function relatedProducts(Product $product)
    {
        $related = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['reviews', 'category']) // العلاقات المطلوبة لحساب البيانات
            ->take(10)
            ->get();

        $formatted = $related->map(function ($item) {
            $originalPrice = $item->price; // أو أي حساب منطقي لو فيه خصومات
            $discountedPrice = $item->saleable?->final_price ?? $item->price;
            $discountPercentage = $originalPrice > 0 ? (($originalPrice - $discountedPrice) / $originalPrice) * 100 : 0;

            return [
                'id' => $item->id,
                'name' => $item->name,
                'main_image' => $item->image ? asset($item->image) : null,
               
                'price_before_discount' => round($originalPrice, 2),
                'price_after_discount' => round($discountedPrice, 2),
                'discount_percentage' => round($discountPercentage, 2),
                'rate' => $item->rate,
                'category' => $item->category->name ?? null,
                'is_favorite' => $item->is_favorite,
                'ratings_count' => $item->reviews->count(),
                'average_rating' => round($item->reviews->avg('rate'), 1),
            ];
        });

        return response()->json([
            'data' => $formatted
        ]);
    }


  
}
