<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line item on a deal. Prices are copied from the product when added, so later price changes don't alter old deals.
 *
 * @property int $id
 * @property int $team_id
 * @property int $deal_id
 * @property ?int $product_id
 * @property string $name
 * @property string $quantity
 * @property int $unit_price Minor units.
 * @property string $discount Percent.
 * @property string $tax_rate Percent.
 * @property int $line_total Minor units, after discount, before tax.
 * @property int $sort
 */
class DealProduct extends Model
{
    use BelongsToWorkspace;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'deal_id',
        'product_id',
        'name',
        'quantity',
        'unit_price',
        'discount',
        'tax_rate',
        'sort',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'unit_price' => 0,
        'discount' => 0,
        'tax_rate' => 0,
        'line_total' => 0,
        'sort' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DealProduct $line): void {
            $line->team_id ??= Deal::query()->withoutGlobalScopes()->whereKey($line->deal_id)->value('team_id');
            $line->line_total = static::calculateTotal((float) $line->quantity, $line->unit_price, (float) $line->discount);
        });

        static::saved(fn (DealProduct $line) => $line->deal?->recalculateAmount());
        static::deleted(fn (DealProduct $line) => $line->deal?->recalculateAmount());
    }

    public static function calculateTotal(float $quantity, int $unitPrice, float $discountPercent): int
    {
        return (int) round($quantity * $unitPrice * (1 - min(max($discountPercent, 0), 100) / 100));
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
