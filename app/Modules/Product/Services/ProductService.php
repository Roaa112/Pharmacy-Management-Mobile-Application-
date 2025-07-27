<?php

namespace App\Modules\Product\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Modules\Product\Resources\ProductCollection;
use App\Modules\Product\Requests\UpdateProductRequest;
use App\Modules\Product\Repositories\ProductsRepository;
use App\Modules\Product\Requests\ListAllProductsRequest;

class ProductService
{
    public function __construct(private ProductsRepository $productsRepository)
    {
    }
     private function handleMainImage(array &$data, $request, Product $product = null): void
        {

            if ($request->hasFile('image')) {
                $image = $request->file('image');

                if (!$image->isValid()) {
                    return;
                }

                // انسخ الصورة مؤقتًا قبل أي عمليات (نتجنب فقدان الملف المؤقت)
                $tmpPath = $image->getPathname();
                $extension = $image->getClientOriginalExtension();

                $filename = uniqid() . '.' . $extension;
                $destinationPath = public_path('products');

                // حذف الصورة القديمة لو فيه منتج
                if ($product && $product->image && file_exists(public_path($product->image))) {
                    try {
                        unlink(public_path($product->image));
                    } catch (\Exception $e) {
                        // Log::error("Error deleting image: " . $e->getMessage());
                    }
                }

                // انسخ الصورة بشكل يدوي لو الملف المؤقت موجود
                if (file_exists($tmpPath)) {
                    copy($tmpPath, $destinationPath . '/' . $filename);
                    $data['image'] = 'products/' . $filename;
                }
            } elseif ($product) {
                $data['image'] = $product->image;
            }
        }
    public function updateProduct(Product $product, UpdateProductRequest $request): ?Product
    {
        $data = $request->validated();

        // معالجة بيانات العروض (Promotion)
        $this->handlePromotionData($data, $request, $product);

        // معالجة الصورة الرئيسية
        $this->handleMainImage($data, $request, $product);

        // تحديث المنتج في الـ Repository
        $product = $this->productsRepository->update($product->id, $data);
        if (!$product) return null;

        // حذف الصور الإضافية (لو مطلوبة)
        if ($request->has('remove_extra_images')) {
            $this->productsRepository->removeExtraImages($product, $request->remove_extra_images);
        }

        // إضافة صور إضافية
        $images = $request->file('images');
        if (is_array($images)) {
            $validImages = array_filter($images, function ($img) {
                return $img && $img->isValid();
            });

            if (!empty($validImages)) {
                $this->productsRepository->addExtraImages($product, $validImages);
            }
        }

        // ربط المشاكل الصحية بالمنتج
        $this->productsRepository->syncHealthIssues($product, $data['health_issues'] ?? []);

        // تحديث المقاسات (الأحجام)
        if ($request->filled('sizes')) {
            $this->productsRepository->replaceSizes($product, $data['sizes']);
        }

        return $product;
    }
    private function handleMultipleImages(Product $product, $request): void
    {

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                if (!$image || !$image->isValid()) {
                    continue;
                }

                $tmpPath = $image->getPathname();

                if (!file_exists($tmpPath)) {
                    continue;
                }

                $filename = uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('products'), $filename);

                $product->productImages()->create([
                    'image_path' => 'products/' . $filename,
                ]);
            }
        }
    }


    private function handlePromotionData(array &$data, $request, Product $product = null): void
        {
            if ($request->filled('promotion_type')) {
                $data['promotion_type'] = $request->promotion_type;

                if ($request->promotion_type === 'discount') {
                    $data['saleable_type'] = \App\Models\Discount::class;
                    $data['saleable_id'] = $request->discount_id;
                } elseif ($request->promotion_type === 'flash_sale') {
                    $data['saleable_type'] = \App\Models\FlashSale::class;
                    $data['saleable_id'] = $request->flash_sale_id;
                } else {
                    $data['saleable_type'] = null;
                    $data['saleable_id'] = null;
                }
            } elseif ($product) {
                $data['promotion_type'] = $product->promotion_type;
                $data['saleable_type'] = $product->saleable_type;
                $data['saleable_id'] = $product->saleable_id;
            }
        }


    public function handleStoreProduct(array $data, $request)
    {
        $this->applyPromotionType($data, $request);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('products'), $filename);
            $data['image'] = 'products/' . $filename;
        }

        $product = $this->createProduct($data);

        $this->handleMultipleImages($product, $request);

        if ($request->filled('health_issues')) {
            $product->healthIssues()->sync($data['health_issues']);
        }

        $this->handleSizes($product, $request->input('sizes', []));

        return $product;
    }

    private function applyPromotionType(array &$data, $request)
    {
        if ($request->filled('promotion_type')) {
            if ($request->promotion_type === 'discount' && $request->filled('discount_id')) {
                $data['saleable_type'] = \App\Models\Discount::class;
                $data['saleable_id'] = $request->discount_id;
            } elseif ($request->promotion_type === 'flash_sale' && $request->filled('flash_sale_id')) {
                $data['saleable_type'] = \App\Models\FlashSale::class;
                $data['saleable_id'] = $request->flash_sale_id;
            }
        }
    }


    private function handleSizes(Product $product, array $sizes)
    {
        foreach ($sizes as $sizeData) {
            $existing = $product->sizes()->where('size', $sizeData['size'])->first();

            if (!$existing) {
                $product->sizes()->create([
                    'size' => $sizeData['size'],
                    'price' => $sizeData['price'],
                    'stock' => $sizeData['stock'],
                ]);
            }
        }
    }


    public function createProduct(array $data): Product
    {
        return $this->productsRepository->create($data);
    }
    public function listAllProducts(array $queryParameters)
        {
            $criteria = (new ListAllProductsRequest)->constructQueryCriteria($queryParameters);
            $products = $this->productsRepository->findAllBy($criteria);

            return [
                'data' => new ProductCollection($products['data']),
                'count' => $products['count']
            ];
        }


    public function constructProductModel($request)
    {
        $healthIssues = array_filter($request['health_issues'] ?? []);

        return [
            'name_ar'        => $request['name_ar'],
            'name_en'        => $request['name_en'],
            'description_ar' => $request['description_ar'],
            'description_en' => $request['description_en'],
            'rate'           => $request['rate'],
            'image'          => $request['image'],
            'health_issues'  => $healthIssues,
            'category_id'    => $request['category_id'],
            'brand_id'       => $request['brand_id'] ?? null,
            'saleable_id'    => $request['saleable_id'] ?? null,
            'saleable_type'  => $request['saleable_type'] ?? null,
            'images'         => $request['images'] ?? [],
        ];
    }








    public function searchProducts(array $queryCriteria)
    {
        $result = $this->productsRepository->executeGetMany(Product::query(), $queryCriteria);

      
        return $result;
    }





    // latest products
    public function getLatestProducts(): array
    {
        $products = Product::with(['brand', 'category', 'sizes', 'saleable', 'productImages'])
            ->latest()
            ->take(10)
            ->get();

        return [
            'data' => $products,
            'count' => $products->count(),
        ];
    }

    private function calculateDiscountPercentage($product)
    {
        $priceBefore = $product->available_sizes->first()['original_price'] ?? 0;
        $priceAfter = $product->final_price;

        if ($priceBefore > 0 && $priceBefore > $priceAfter) {
            return round((($priceBefore - $priceAfter) / $priceBefore) * 100, 2);
        }

        return 0;
    }



   // top descount
    public function getTopDiscountProducts(): array
    {
        // 1. Get top percent and fixed discount rules (active only)
        $topPercentRule = DB::table('discount_rules')
            ->where('discount_type', 'percent')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderByDesc('discount_value')
            ->first();

        $topFixedRule = DB::table('discount_rules')
            ->where('discount_type', 'fixed')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderByDesc('discount_value')
            ->first();

        // 2. Fetch products for each rule
        $productsPercent = $topPercentRule ? $this->getProductsBytopDiscountRule($topPercentRule->id) : collect();
        $productsFixed = $topFixedRule ? $this->getProductsByDiscountRule($topFixedRule->id) : collect();

        // 3. Transform products to return format
        $transform = fn($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'main_image' => $product->image_url,
            'images' => $product->images,
            'brand' => $product->brand->name ?? null,
            'category' => $product->category->name ?? null,
            'price_before_discount' => $product->available_sizes->first()['original_price'] ?? 0,
            'price_after_discount' => $product->final_price,
            'discount_type' => $product->discount_type,
            'is_favorite' => $product->is_favorite,
            'rate' => $product->rate,
            'ratings_count' => $product->ratings_count,
            'average_rating' => $product->average_rating,
        ];

        return [
            'data' => [
                'top_percent_discount' => [
                    'discount_value' => $topPercentRule->discount_value ?? null,
                    'products' => $productsPercent->map($transform),
                ],
                'top_fixed_discount' => [
                    'discount_value' => $topFixedRule->discount_value ?? null,
                    'products' => $productsFixed->map($transform),
                ],
            ],
            'count' => $productsPercent->count() + $productsFixed->count(),
        ];
    }

    private function getProductsBytopDiscountRule($ruleId)
    {
        $targets = DB::table('discount_rule_targets')
            ->where('discount_rule_id', $ruleId)
            ->get();

        $productQuery = Product::query();

        $productQuery->where(function ($query) use ($targets) {
            foreach ($targets as $target) {
                if ($target->target_type === 'product') {
                    $query->orWhere('id', $target->target_id);
                } elseif ($target->target_type === 'brand') {
                    $query->orWhere('brand_id', $target->target_id);
                } elseif ($target->target_type === 'category') {
                    $query->orWhere('category_id', $target->target_id);
                }
            }
        });

        return $productQuery
            ->with(['sizes', 'productImages', 'brand', 'category'])
            ->get();
    }

    // products on sale

    public function listAllProductsOnSale(): array
    {
        // 1. Get top percent and fixed discount rules (active only)
        $topPercentRule = DB::table('discount_rules')
            ->where('discount_type', 'percent')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderByDesc('discount_value')
            ->first();

        $topFixedRule = DB::table('discount_rules')
            ->where('discount_type', 'fixed')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderByDesc('discount_value')
            ->first();

        // 2. Fetch products for each rule
        $productsPercent = $topPercentRule ? $this->getProductsByDiscountRule($topPercentRule->id) : collect();
        $productsFixed = $topFixedRule ? $this->getProductsByDiscountRule($topFixedRule->id) : collect();

        // 3. Transform products to return format
        $transform = fn($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'main_image' => $product->image_url,
            'images' => $product->images,
            'brand' => $product->brand->name ?? null,
            'category' => $product->category->name ?? null,
            'price_before_discount' => $product->available_sizes->first()['original_price'] ?? 0,
            'price_after_discount' => $product->final_price,
            'discount_type' => $product->discount_type,
            'is_favorite' => $product->is_favorite,
            'rate' => $product->rate,
            'ratings_count' => $product->ratings_count,
            'average_rating' => $product->average_rating,
        ];

        return [
            'data' => [
                'top_percent_discount' => [
                    'discount_value' => $topPercentRule->discount_value ?? null,
                    'products' => $productsPercent->map($transform),
                ],
                'top_fixed_discount' => [
                    'discount_value' => $topFixedRule->discount_value ?? null,
                    'products' => $productsFixed->map($transform),
                ],
            ],
            'count' => $productsPercent->count() + $productsFixed->count(),
        ];
    }
    private function getProductsByDiscountRule($ruleId)
    {
        $targets = DB::table('discount_rule_targets')
            ->where('discount_rule_id', $ruleId)
            ->get();

        $productIds = collect();

        foreach ($targets as $target) {
            if ($target->target_type === 'product') {
                $productIds->push($target->target_id);
            } elseif ($target->target_type === 'brand') {
                $productIds = $productIds->merge(
                    Product::where('brand_id', $target->target_id)->pluck('id')
                );
            } elseif ($target->target_type === 'category') {
                $productIds = $productIds->merge(
                    Product::where('category_id', $target->target_id)->pluck('id')
                );
            }
        }

        return Product::with(['sizes', 'productImages', 'brand', 'category'])
            ->whereIn('id', $productIds->unique())
            ->get();
    }
   // show product
    public function getProductById($id)
    {
        return $this->productsRepository->find($id);
    }

   // delete products
   public function deleteProduct($id)
    {
        return $this->productsRepository->delete($id);
    }


}
