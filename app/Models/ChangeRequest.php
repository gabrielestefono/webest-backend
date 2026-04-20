<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequest extends Model
{
    use HasFactory;

    public const STATUSES = [
        'requested' => 'Solicitada',
        'quoted' => 'Orçada',
        'client_approved' => 'Aprovada pelo cliente',
        'payment_pending' => 'Aguardando pagamento',
        'paid' => 'Paga',
        'rejected' => 'Rejeitada',
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
            'quoted' => 'info',
            'client_approved' => 'primary',
            'payment_pending' => 'warning',
            'paid' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
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
