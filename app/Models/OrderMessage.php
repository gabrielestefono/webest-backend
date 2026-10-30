<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderMessage extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'sender_id',
        'sender_role',
        'turn_number',
        'current_content',
        'is_locked',
        'edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'sender_id' => 'integer',
            'turn_number' => 'integer',
            'is_locked' => 'boolean',
            'edited_at' => 'datetime',
        ];
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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return HasMany<OrderMessageVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(OrderMessageVersion::class, 'order_message_id')->orderBy('version_number');
    }
}
