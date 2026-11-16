<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Producto del catálogo de la tienda.
 *
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property float $price
 * @property int $stock
 * @property string|null $image_url
 * @property bool $is_active
 */
class Product extends Model
{
    use HasFactory;

    /**
     * Valores por defecto para nuevas instancias (reflejan los defaults de la migración).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stock' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'sku',
        'name',
        'description',
        'category',
        'price',
        'stock',
        'image_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Renglones de orden que referencian este producto.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Filtra únicamente productos activos (visibles en el catálogo público).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Determina si hay suficiente stock disponible para una cantidad dada.
     */
    public function hasStock(int $quantity): bool
    {
        return $this->stock >= $quantity;
    }
}
