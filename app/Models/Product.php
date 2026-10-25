<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\PurgesTrashedRecords;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $team_id
 * @property ?int $category_id
 * @property string $sku
 * @property string $name
 * @property ?string $description
 * @property int $unit_price Minor units.
 * @property string $tax_rate Percent, for example "13.00".
 * @property bool $is_active
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use AuditsChanges, BelongsToWorkspace, HasFactory, PurgesTrashedRecords, SoftDeletes;

    public const DEFAULT_TAX_RATE = 13;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'category_id',
        'sku',
        'name',
        'description',
        'unit_price',
        'tax_rate',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unit_price' => 0,
        'tax_rate' => self::DEFAULT_TAX_RATE,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
}
