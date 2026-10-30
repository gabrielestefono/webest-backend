<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proposal extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending' => 'Pendente',
        'accepted' => 'Aceita',
        'countered' => 'Contra-proposta enviada',
        'rejected' => 'Rejeitada',
        'cancelled' => 'Cancelada',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'sender_id',
        'content',
        'price',
        'estimated_days',
        'is_accepted',
        'status',
        'superseded_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'sender_id' => 'integer',
            'price' => 'decimal:2',
            'estimated_days' => 'integer',
            'is_accepted' => 'boolean',
            'superseded_at' => 'datetime',
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
}
