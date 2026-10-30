<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public const BILLING_TYPES = [
        'one_time' => 'Pagamento Único',
        'recurring' => 'Assinatura Recorrente',
    ];

    public const BILLING_INTERVALS = [
        'month' => 'Mensal',
        'year' => 'Anual',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'base_price',
        'billing_type',
        'billing_interval',
        'stripe_price_id',
        'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function billingLabel(): string
    {
        if ($this->billing_type === 'recurring') {
            $intervalLabel = self::BILLING_INTERVALS[(string) $this->billing_interval] ?? 'Recorrente';

            return "Assinatura ({$intervalLabel})";
        }

        return 'Pagamento Único';
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return BelongsToMany<Order, $this>
     */
    public function bundledOrders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_products')->withTimestamps();
    }
}
