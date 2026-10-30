<?php

namespace App\Actions\Product;

use App\Dto\Product\ListProductsDto;
use App\Models\Product;

class ListProductsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(ListProductsDto $dto): array
    {
        $items = Product::query()
            ->where('active', true)
            ->orderBy('billing_type')
            ->orderBy('id')
            ->get()
            ->map(static fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'base_price' => $product->base_price !== null ? (float) $product->base_price : null,
                'billing_type' => $product->billing_type ?? 'one_time',
                'billing_interval' => $product->billing_interval,
                'billing_label' => $product->billingLabel(),
            ])
            ->all();

        return array_values($items);
    }
}
