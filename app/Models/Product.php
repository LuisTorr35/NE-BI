<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price'     => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Etiquetas legibles del catalogo (mapean a PreferedOrderCat del dataset) */
    public const CATEGORIES = [
        'laptops_accesorios' => 'Laptops y Accesorios',
        'celulares'          => 'Celulares y Smartphones',
        'wearables'          => 'Wearables',
        'electro_cocina'     => 'Electro de Cocina',
        'linea_blanca'       => 'Línea Blanca y Otros',
    ];

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
