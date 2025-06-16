<?php

namespace App\Modules\Discount\Requests;

use App\Modules\Shared\Requests\BaseRequest;

class ListAllDiscountsRequest extends BaseRequest
{
    public function getFilters(): array
    {
        return [
            'title' => 'title',
            'precentage' => 'precentage',
            'is_active' => 'is_active',
            'start_date' => 'start_date',
            'end_date' => 'end_date',
        ];
    }

    public function constructQueryCriteria(array $queryParameters)
    {
        $filters = $this->setFilters(data_get($queryParameters, 'filters'));
        return array_merge($this->constructBaseGetQuery($queryParameters), ['filters' => $filters]);
    }
}
