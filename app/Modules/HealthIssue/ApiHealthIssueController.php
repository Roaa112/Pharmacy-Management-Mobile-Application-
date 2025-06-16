<?php

namespace App\Modules\HealthIssue;

use App\Models\HealthIssue;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\HealthIssue\Services\HealthIssueService;
use App\Modules\HealthIssue\Requests\ListAllHealthIssuesRequest;


class ApiHealthIssueController extends Controller
{
    public function __construct(private HealthIssueService $healthIssueService)
    {
    }
    public function listAllHealthIssues(ListAllHealthIssuesRequest $request)
    {
        $healthIssues = $this->healthIssueService->listAllHealthIssues($request->all());
        return successJsonResponse(
            data_get($healthIssues, 'data'),
            __('healthIssues.success.get_all_brands'),
            data_get($healthIssues, 'count')
        );
    }
 public function listAllHealthIssuesProducts($id)
{
    $limit = request()->get('limit', 10); // default 10
    $offset = request()->get('offset', 0);

    // Get the health issue with its products (without eager loading yet)
    $healthIssue = HealthIssue::find($id);

    if (!$healthIssue) {
        return errorJsonResponse(__('HealthIssues.errors.not_found'), 404);
    }

    // Load only a limited number of products related to this health issue
    $products = $healthIssue->products()
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

    // Attach products to the health issue manually
    $healthIssue->setRelation('products', $products);

    return successJsonResponse(
        $healthIssue,
        __('HealthIssues.success.get_health_issue_with_products'),
        $products->count()
    );
}

    
    
}
