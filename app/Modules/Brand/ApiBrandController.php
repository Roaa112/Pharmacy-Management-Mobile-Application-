<?php

namespace App\Modules\Brand;

use App\Models\Brand;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Brand\Services\BrandService;
use App\Modules\Brand\Requests\ListAllBrandsRequest;


class ApiBrandController extends Controller
{
    public function __construct(private BrandService $brandService)
    {
    }
    public function listAllBrands(ListAllBrandsRequest $request)
    {
      
        $brands = $this->brandService->listAllBrands($request->all()); 
        return successJsonResponse(
            data_get($brands, 'data'),
            __('Brands.success.get_all_brands'),
            data_get($brands, 'count')
        );
    }

public function listAllBrandsProducts($id)
{
    $limit = request()->get('limit', 10); // default limit 10
    $offset = request()->get('offset', 0);

    // Get brand without loading products yet
    $brand = Brand::find($id);

    if (!$brand) {
        return errorJsonResponse(__('Brands.errors.not_found'), 404);
    }

    // Load products with relations and apply pagination
    $products = $brand->products()
        ->with(['saleable', 'category', 'sizes']) // eager load related models
        ->offset($offset)
        ->limit($limit)
        ->get();

    // Apply discount logic
    $products = $products->map(function ($product) {
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

        $product->price_before_discount = round($originalPrice, 2);
        $product->price_after_discount = round($discountedPrice, 2);
        $product->discount_percentage = round($discountPercentage, 2);
        $product->category_name = $product->category->name ?? null;

        return $product;
    });

    // Attach filtered products to the brand model
    $brand->setRelation('products', $products);

    return successJsonResponse(
        $brand,
        __('Brands.success.get_brand_with_products'),
        $products->count()
    );
}
}
