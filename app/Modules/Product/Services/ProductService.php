<?php

namespace App\Modules\Product\Services;

use App\Models\Product;
use App\Modules\Product\Resources\ProductCollection;
use App\Modules\Product\Repositories\ProductsRepository;
use App\Modules\Product\Requests\ListAllProductsRequest;
use App\Modules\Product\Requests\UpdateProductRequest;

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
        }public function updateProduct(Product $product, UpdateProductRequest $request): ?Product
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

    public function deleteProduct($id)
    {
        return $this->productsRepository->delete($id);
    }



    public function getLatestProducts(): array
    {
        $products = $this->productsRepository->getLatestProducts();

        $transformed = $products->map(function ($product) {
            $originalPrice = (float) $product->price;
            $discountedPrice = $originalPrice;
            $discountPercentage = 0;

            if (isset($product->saleable) && $product->saleable->is_active) {
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
                'main_image' => $product->image ? asset( $product->image) : null,
                 'images' => $product->images,
                'brand' => $product->brand->name ?? null,
                'category' => $product->category->name ?? null,
                'price_before_discount' => round($originalPrice, 2),
                'price_after_discount' => round($discountedPrice, 2),
                'discount_percentage' => round($discountPercentage, 2),
                'rate' => $product->rate,
                'is_on_sale' => $discountPercentage > 0,
                 'is_favorite' => $product->is_favorite,
                  'ratings_count' => $product->ratings_count,
                'average_rating' => $product->average_rating,

            ];
        });

        return [
            'data' => $transformed,
            'count' => $transformed->count(),
        ];
    }

    public function getProductById($id)
    {
        return $this->productsRepository->find($id);
    }


    public function getTopDiscountProducts(): array
    {
        $products = $this->productsRepository->getProductsOfTopDiscount();

        $transformed = $products->map(function ($product) {
            $originalPrice = (float) $product->price;
            $discountedPrice = $originalPrice;
            $discountPercentage = 0;

            if (isset($product->saleable) && $product->saleable->is_active) {
                if ($product->saleable_type === \App\Models\Discount::class) {
                    $discountedPrice = $originalPrice - ($originalPrice * $product->saleable->precentage / 100);
                    $discountPercentage = $product->saleable->precentage;
                } elseif ($product->saleable_type === \App\Models\FlashSale::class) {
                    $discountedPrice = $originalPrice - $product->saleable->discount_value;
                    $discountPercentage = ($product->saleable->discount_value / $originalPrice) * 100;
                }
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'main_image' => $product->image ? asset($product->image) : null,
                 'images' => $product->images,
                'price_before_discount' => round($originalPrice, 2),
                'price_after_discount' => round($discountedPrice, 2),
                'discount_percentage' => round($discountPercentage, 2),
                'rate' => $product->rate,
                'category' => $product->category->name ?? null,
                'is_favorite' => $product->is_favorite,
                'ratings_count' => $product->ratings_count,
                'average_rating' => $product->average_rating,
            ];
        });

        return [
            'data' => $transformed,
            'count' => $transformed->count(),
        ];
    }

    public function searchProducts(array $queryCriteria)
    {
        $result = $this->productsRepository->executeGetMany(Product::query(), $queryCriteria);

        // نعد على كل منتج ونضي الخصم
        $result['data'] = $result['data']->map(function ($product) {
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

            return $productArray;
        });

        return $result;
    }


}
