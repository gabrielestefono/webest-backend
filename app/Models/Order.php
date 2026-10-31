<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending' => 'Pendente',
        'under_review' => 'Em análise',
        'proposal_sent' => 'Proposta enviada',
        'proposal_negotiation' => 'Em negociação',
        'approved' => 'Aprovado',
        'awaiting_contract' => 'Aguardando assinatura',
        'awaiting_payment' => 'Aguardando pagamento',
        'paid' => 'Pago',
        'awaiting_development' => 'Aguardando desenvolvimento',
        'in_progress' => 'Em desenvolvimento',
        'homologation' => 'Em homologação / teste',
        'adjustment_review' => 'Ajustes em triagem',
        'in_revision' => 'Em revisão',
        'review' => 'Em revisão',
        'done' => 'Concluído',
        'cancelled' => 'Cancelado',
        'rejected' => 'Rejeitado',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'parent_order_id',
        'product_id',
        'title',
        'description',
        'figma_url',
        'status',
        'final_price',
        'estimated_days',
        'contract_url',
        'contract_signed_at',
        'stripe_checkout_url',
        'stripe_session_id',
        'staging_url',
        'repository_url',
        'deadline',
        'client_completed_at',
        'provider_completed_at',
        'warranty_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'parent_order_id' => 'integer',
            'product_id' => 'integer',
            'final_price' => 'decimal:2',
            'estimated_days' => 'integer',
            'contract_signed_at' => 'datetime',
            'deadline' => 'datetime',
            'client_completed_at' => 'datetime',
            'provider_completed_at' => 'datetime',
            'warranty_until' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return self::STATUSES;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    /**
     * @return list<string>
     */
    public static function activeStatuses(): array
    {
        return array_values(array_diff(array_keys(self::STATUSES), [
            'rejected',
            'cancelled',
            'done',
        ]));
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'gray',
            'under_review' => 'info',
            'proposal_sent', 'proposal_negotiation' => 'warning',
            'approved', 'paid', 'done' => 'success',
            'awaiting_contract', 'awaiting_payment', 'adjustment_review' => 'warning',
            'awaiting_development' => 'info',
            'in_progress' => 'primary',
            'homologation', 'in_revision', 'review' => 'info',
            'rejected', 'cancelled' => 'danger',
            default => 'gray',
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'order_products')->withTimestamps();
    }

    /**
     * @return HasMany<OrderAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(OrderAttachment::class);
    }

    /**
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /**
     * @return HasMany<OrderMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(OrderMessage::class);
    }

    /**
     * @return HasMany<OrderAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }

    /**
     * @return HasMany<OrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    /**
     * @return HasOne<Project, $this>
     */
    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    /**
     * @return HasOne<OrderScope, $this>
     */
    public function scope(): HasOne
    {
        return $this->hasOne(OrderScope::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function childOrders(): HasMany
    {
        return $this->hasMany(self::class, 'parent_order_id');
    }

    public function hasCompleteScope(): bool
    {
        $scope = $this->relationLoaded('scope')
            ? $this->scope
            : $this->scope()->first();

        return $scope instanceof OrderScope && $scope->isComplete();
    }
}
