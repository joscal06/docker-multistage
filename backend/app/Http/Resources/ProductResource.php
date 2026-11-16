<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Product',
    title: 'Producto',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'sku', type: 'string', example: 'ELEC-0001'),
        new OA\Property(property: 'name', type: 'string', example: 'Laptop Ultradelgada 14" 16GB/512GB'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Laptop ligera con procesador de última generación.'),
        new OA\Property(property: 'category', type: 'string', nullable: true, example: 'Laptops'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 799.99),
        new OA\Property(property: 'stock', type: 'integer', example: 25),
        new OA\Property(property: 'image_url', type: 'string', nullable: true, example: 'https://picsum.photos/seed/laptop/600'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
