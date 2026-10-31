<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderScope extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'included_items',
        'excluded_items',
        'delivery_criteria',
        'revision_rules',
        'client_responsibilities',
        'updated_by_id',
        'locked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'included_items' => 'array',
            'excluded_items' => 'array',
            'delivery_criteria' => 'array',
            'revision_rules' => 'array',
            'client_responsibilities' => 'array',
            'updated_by_id' => 'integer',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * Normalize any JSON list column into a clean non-empty string list.
     *
     * @param  mixed  $raw
     * @return list<string>
     */
    public static function normalizeItems(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $entry) {
            if (! is_string($entry) && ! is_numeric($entry)) {
                continue;
            }
            $trimmed = trim((string) $entry);
            if ($trimmed !== '') {
                $items[] = $trimmed;
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    public function normalizedIncludedItems(): array
    {
        return self::normalizeItems($this->included_items);
    }

    /**
     * @return list<string>
     */
    public function normalizedExcludedItems(): array
    {
        return self::normalizeItems($this->excluded_items);
    }

    /**
     * @return list<string>
     */
    public function normalizedDeliveryCriteria(): array
    {
        return self::normalizeItems($this->delivery_criteria);
    }

    /**
     * @return list<string>
     */
    public function normalizedRevisionRules(): array
    {
        return self::normalizeItems($this->revision_rules);
    }

    /**
     * @return list<string>
     */
    public function normalizedClientResponsibilities(): array
    {
        return self::normalizeItems($this->client_responsibilities);
    }

    public function isComplete(): bool
    {
        return $this->normalizedIncludedItems() !== []
            && $this->normalizedExcludedItems() !== []
            && $this->normalizedDeliveryCriteria() !== []
            && $this->normalizedRevisionRules() !== []
            && $this->normalizedClientResponsibilities() !== [];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}

