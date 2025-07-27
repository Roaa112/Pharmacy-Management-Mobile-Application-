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

    $category = Category::find($id);

    if (!$category) {
        return errorJsonResponse(__('Categories.errors.not_found'), 404);
    }

    $products = $category->products()
        ->with(['saleable', 'category']) // load relations
        ->offset($offset)
        ->limit($limit)
        ->get();

    $products = $products->map(function ($product) {
        $productArray = $product->toArray();
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
