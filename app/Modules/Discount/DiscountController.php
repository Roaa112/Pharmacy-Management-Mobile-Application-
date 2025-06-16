<?php

namespace App\Modules\Discount;

use App\Models\Discount;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Discount\Services\DiscountService;
use App\Modules\Discount\Requests\StoreDiscountRequest;
use App\Modules\Discount\Requests\UpdateDiscountRequest;

class DiscountController extends Controller
{
    public function __construct(private DiscountService $discountService)
    {
    }

    public function index(Request $request)
    {
        $discounts = $this->discountService->listAllDiscounts($request->all());
    
        return view('dashboard.Discounts.index', [
            'discounts' => $discounts['data'], 
        ]);
    }
    

    public function store(StoreDiscountRequest $request)
    {

        $this->discountService->createDiscount($request->validated());
        return redirect()->back()->with('success', 'Discount created successfully!');
    }
  
    public function update(UpdateDiscountRequest $request, $id)
    {
     
        $this->discountService->updateDiscount($id, $request->validated());
        return redirect()->back()->with('success', 'Discount updated successfully!');
    }

    public function destroy($id)
    {
        $this->discountService->deleteDiscount($id);
        return redirect()->back()->with('success', 'Discount deleted successfully!');
    }
}
