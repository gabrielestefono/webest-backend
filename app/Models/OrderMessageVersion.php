<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMessageVersion extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_message_id',
        'version_number',
        'content',
        'edited_by_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OrderMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(OrderMessage::class, 'order_message_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_id');
    }
}

