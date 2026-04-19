<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending' => 'Pendente',
        'under_review' => 'Em análise',
        'proposal_sent' => 'Proposta enviada',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
        'awaiting_payment' => 'Aguardando pagamento',
        'paid' => 'Pago',
        'in_progress' => 'Em andamento',
        'review' => 'Em revisão',
        'done' => 'Concluído',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'title',
        'description',
        'status',
    ];

    public static function statusOptions(): array
    {
        return self::STATUSES;
    }

    /**
     * @return list<string>
     */
    public static function activeStatuses(): array
    {
        return array_values(array_diff(array_keys(self::STATUSES), [
            'rejected',
            'done',
        ]));
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'gray',
            'under_review' => 'info',
            'proposal_sent' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'awaiting_payment' => 'warning',
            'paid' => 'success',
            'in_progress' => 'primary',
            'review' => 'info',
            'done' => 'success',
            default => 'gray',
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }
}
