<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    public const PAYMENT_STATUSES = [
        'pending' => 'Pendente',
        'paid' => 'Pago',
        'rejected' => 'Rejeitado',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $project): void {
            if (! $project->wasChanged('payment_status')) {
                return;
            }

            $project->syncOrderStatusFromPayment();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'payment_link',
        'payment_status',
        'github_url',
        'deploy_url',
        'current_progress',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_progress' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    public function syncOrderStatusFromPayment(): void
    {
        $order = $this->order;

        if (! $order) {
            return;
        }

        $targetStatus = match ($this->payment_status) {
            'paid', 'approved' => 'paid',
            'pending' => 'awaiting_payment',
            'rejected' => 'rejected',
            default => null,
        };

        if (! $targetStatus || $order->status === $targetStatus) {
            return;
        }

        $order->update([
            'status' => $targetStatus,
        ]);
    }
}
