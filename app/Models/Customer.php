<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'         => 'hashed',
            'is_seeded'        => 'boolean',
            'actual_churn'     => 'boolean',
            'complain'         => 'boolean',
            'churn_scored_at'  => 'datetime',
            'churn_probability'=> 'float',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Accion de retencion sugerida segun el nivel de churn (PLAN.md secc. 7) */
    public const ACCIONES = [
        'alto'     => 'Cupón de descuento inmediato',
        'medio'    => 'Correo personalizado con su categoría favorita',
        'moderado' => 'Campaña de remarketing',
        'bajo'     => 'Sin acción / fidelización normal',
    ];

    public function accionSugerida(): string
    {
        return self::ACCIONES[$this->churn_level] ?? '—';
    }
}
