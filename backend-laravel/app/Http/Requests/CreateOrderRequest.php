<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level middleware handles auth; this only validates shape
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'uuid'],
            'order_type' => ['required', 'in:dine_in,takeaway,delivery,online_pickup'],
            'source' => ['nullable', 'in:pos,web,mobile,kiosk'],
            'table_id' => ['nullable', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'device_id' => ['nullable', 'uuid'],
            'discount_code' => ['nullable', 'string', 'max:40'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'uuid'],
            'items.*.item_variant_id' => ['nullable', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.modifier_ids' => ['nullable', 'array'],
            'items.*.modifier_ids.*' => ['uuid'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
