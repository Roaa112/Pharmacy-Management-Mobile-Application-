<?php

namespace App\Modules\Brand\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateBrandRequest extends FormRequest
{
    public function rules(): array
    {
        
        return [
            'name' => 'sometimes|string|max:255',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', 
            'quantity_per_time' => 'sometimes|integer|min:1',
            'repeat_type' => 'sometimes|in:every_day,specific_days,once',
            'times' => 'sometimes|array|min:1',
            'times.*' => 'sometimes|date_format:H:i',
            'days' => 'nullable|array',
           'days.*' => 'in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
        ];
    }
}
