<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'available_stock' => ['required', 'integer', 'min:0'],
            'reserved_stock' => ['required', 'integer', 'min:0'],
        ];
    }
}
