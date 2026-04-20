<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequest extends Model
{
    use HasFactory;

    public const STATUSES = [
        'requested' => 'Pedido de alteração',
        'awaiting_quote' => 'Aguardando orçamento',
        'quoted' => 'Orçada',
        'revision' => 'Em revisão',
        'client_approved' => 'Aprovada pelo cliente',
        'payment_pending' => 'Aguardando pagamento',
        'paid' => 'Paga',
        'pending_development' => 'Pendente desenvolvimento',
        'rejected' => 'Rejeitada',
    ];

    public const FINAL_STATUSES = [
        'rejected',
        'pending_development',
    ];

    public const ALLOWED_TRANSITIONS = [
        'requested' => ['awaiting_quote', 'rejected'],
        'awaiting_quote' => ['quoted', 'rejected'],
        'quoted' => ['client_approved', 'rejected', 'revision'],
        'revision' => ['requested'],
        'client_approved' => ['payment_pending'],
        'payment_pending' => ['paid', 'rejected'],
        'paid' => ['pending_development'],
        'pending_development' => [],
        'rejected' => [],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'requester_id',
        'description',
        'status',
        'impact_price',
        'payment_link',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'impact_price' => 'decimal:2',
            'status' => 'string',
        ];
    }

    public static function statusOptions(): array
    {
        return self::STATUSES;
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'requested' => 'warning',
            'awaiting_quote' => 'info',
            'quoted' => 'info',
            'revision' => 'primary',
            'client_approved' => 'primary',
            'payment_pending' => 'warning',
            'paid' => 'success',
            'pending_development' => 'gray',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    public function canTransitionTo(string $targetStatus): bool
    {
        $currentStatus = (string) $this->status;

        return in_array($targetStatus, self::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true);
    }

    public function isFinalStatus(): bool
    {
        return in_array((string) $this->status, self::FINAL_STATUSES, true);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
