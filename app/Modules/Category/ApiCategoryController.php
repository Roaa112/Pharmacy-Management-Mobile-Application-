<?php

namespace App\Modules\Category;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Product\Services\ProductService;
use App\Modules\Category\Services\CategoryService;
use App\Modules\Category\Requests\ListAllcategoriesRequest;


class ApiCategoryController extends Controller
{
    public function __construct(private CategoryService $categoryService,private ProductService $productService)
    {
    }
    public function listAllCategories(ListAllcategoriesRequest $request)
    {
        $categories = $this->categoryService->listAllCategories($request->all());
        return successJsonResponse(
            data_get($categories, 'data'),
            __('categories.success.get_all_brands'),
            data_get($categories, 'count')
        );
    }
    
   public function listAllCategoriesProducts($id)
{
    $limit = request()->get('limit', 10);
    $offset = request()->get('offset', 0);

    // Get category without loading products yet
    $category = Category::find($id);

    if (!$category) {
        return errorJsonResponse(__('Categories.errors.not_found'), 404);
    }

    // Load paginated products with related models
    $products = $category->products()
        ->with(['saleable', 'category']) // eager load relations
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

        $productArray = $product->toArray();
        $productArray['price_before_discount'] = round($originalPrice, 2);
        $productArray['price_after_discount'] = round($discountedPrice, 2);
        $productArray['discount_percentage'] = round($discountPercentage, 2);
        $productArray['category_name'] = $product->category->name ?? null;

        return $productArray;
    });

    return successJsonResponse(
        $products,
        __('Categories.success.get_category_with_products_and_children'),
        $products->count()
    );
}

    
    
    public function searchCategoryProducts(Request $request, $categoryId)
    {
        $queryCriteria = $request->all();
        $queryCriteria['filters']['category_id'] = [
            'operator' => '=',
            'value' => $categoryId
        ];
    
        // استدعاء ن الـ ProductService
        $products = $this->productService->searchProducts($queryCriteria);
    
        return successJsonResponse(
            data_get($products, 'data'),
            __('Categories.success.get_category_with_filtered_products'),
            data_get($products, 'count')
        );
    }
    
}
