<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

class ProductCards extends Field
{
    protected string $view = 'filament.forms.components.product-cards';

    /**
     * @var Collection<int, mixed>|array<int, mixed>|Closure
     */
    protected Collection|array|Closure $products = [];

    /**
     * @param  Collection<int, mixed>|array<int, mixed>|Closure  $products
     */
    public function products(Collection|array|Closure $products): static
    {
        $this->products = $products;

        return $this;
    }

    /**
     * @return Collection<int, mixed>|array<int, mixed>
     */
    public function getProducts(): Collection|array
    {
        return $this->evaluate($this->products);
    }
}
