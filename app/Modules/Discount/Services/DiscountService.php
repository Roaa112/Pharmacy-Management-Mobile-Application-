<?php

namespace App\Modules\Discount\Services;

use App\Models\Discount;

use App\Modules\Discount\Resources\DiscountCollection;
use App\Modules\Discount\Repositories\DiscountsRepository;
use App\Modules\Discount\Requests\ListAllDiscountsRequest;

class DiscountService
{
    public function __construct(private DiscountsRepository $discountsRepository)
    {
    }
  
    public function createDiscount($request)
    {
        $discount = $this->constructDiscountModel($request);
        return $this->discountsRepository->create($discount);
    }

    public function updateDiscount($id, $request)
    {
       
        $discount = $this->constructDiscountModel($request); 
      
        return $this->discountsRepository->update($id, $discount);
    }

    public function deleteDiscount($id)
    {
        return $this->discountsRepository->delete($id);
    }

    public function listAllDiscounts(array $queryParameters)
    {
      
        $listAllDiscounts= (new ListAllDiscountsRequest)->constructQueryCriteria($queryParameters);
        $discounts= $this->discountsRepository->findAllBy($listAllDiscounts );

        return [
            'data' => new DiscountCollection($discounts['data']),
            'count' => $discounts['count']
        ];
    }

    public function getDiscountById($id)
    {
        return $this->discountsRepository->find($id);
    }

    public function constructDiscountModel($request)
{
    $discountModel = [
        'title' => $request['title'],
        'precentage' => $request['precentage'],
        'start_date' => $request['start_date'],
        'end_date' => $request['end_date'] ?? null,
        'expire_date' => $request['expire_date'] ?? null,
        'is_active' => $request['is_active'] ?? true,
    ];

    return $discountModel;
}

    
   
  
}
