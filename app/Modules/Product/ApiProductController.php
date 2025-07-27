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

    return successJsonResponse(
        $products['data'],
        __('Products.success.get_all_brands'),
        $products['count']
    );
}


    public function listAllProductsOnSale(ListAllProductsRequest $request)
    {
        $products = $this->productService->listAllProductsOnSale($request->all());



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




    public function relatedProducts($productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found.'
            ], 404);
        }

        $related = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['reviews', 'category'])
            ->take(10)
            ->get();

        return response()->json([
            'data' => $related
        ]);
    }




}
