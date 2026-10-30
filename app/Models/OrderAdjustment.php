<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAdjustment extends Model
{
    use HasFactory;

    public const DECISIONS = [
        'pending' => 'Sob Triagem Contratual',
        'approved_in_scope' => 'Aprovado no Escopo (Sem Custo)',
        'addendum_issued' => 'Termo Aditivo Emitido',
        'addendum_countered' => 'Contra-proposta de Aditivo Enviada',
        'addendum_accepted' => 'Termo Aditivo Aceito e Pago',
        'addendum_rejected' => 'Aditivo Recusado (Mantido Escopo Original)',
        'rejected' => 'Reprovado (Fora do Contrato)',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'requester_id',
        'client_description',
        'decision',
        'provider_notes',
        'billing_mode',
        'estimated_hours',
        'hourly_rate',
        'addendum_scope',
        'addendum_price',
        'counter_price',
        'counter_notes',
        'addendum_extra_days',
        'contract_reference',
        'stripe_payment_link',
        'stripe_session_id',
        'payment_status',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'requester_id' => 'integer',
            'estimated_hours' => 'integer',
            'hourly_rate' => 'decimal:2',
            'addendum_price' => 'decimal:2',
            'counter_price' => 'decimal:2',
            'addendum_extra_days' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function decisionLabel(): string
    {
        return self::DECISIONS[$this->decision] ?? $this->decision;
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
