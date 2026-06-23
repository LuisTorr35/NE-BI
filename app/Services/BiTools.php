<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Herramientas seguras de Business Intelligence para el chatbot.
 *
 * Cada método es una consulta Eloquent ACOTADA (sin SQL libre): el modelo Llama
 * solo puede pedir estas funciones con parámetros validados, nunca ejecutar SQL.
 * Así el chatbot queda "anclado" a datos reales y no puede inventar clientes.
 */
class BiTools
{
    /** Tope de filas que se devuelven al modelo (controla tokens y costo). */
    public const LIMITE_MAX = 50;

    /** Etiquetas legibles de categoría (valor del dataset => nombre de tienda). */
    public const CATEGORIAS = [
        'Mobile Phone'       => 'Celulares',
        'Laptop & Accessory' => 'Laptops y accesorios',
        'Fashion'            => 'Wearables',
        'Grocery'            => 'Electro cocina',
        'Others'             => 'Línea blanca',
    ];

    /**
     * Definición de las herramientas en formato OpenAI/Groq (function calling).
     * Se envía al modelo para que sepa qué puede pedir.
     */
    public static function definiciones(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'resumen_riesgo',
                    'description' => 'Devuelve el conteo total de clientes por nivel de riesgo de abandono (alto, medio, moderado, bajo) y el total general. Úsalo para preguntas de cuántos clientes hay en cada nivel.',
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'clientes_en_riesgo',
                    'description' => 'Lista de clientes en riesgo de abandono, ordenados por probabilidad de mayor a menor. Permite filtrar por nivel, categoría favorita y ciudad. Úsalo para "quiénes son los clientes con mayor riesgo", "dame los de alto riesgo en celulares", etc.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nivel' => [
                                'type' => 'string',
                                'enum' => ['alto', 'medio', 'moderado', 'bajo'],
                                'description' => 'Nivel de riesgo a filtrar. Si se omite, trae alto+medio+moderado.',
                            ],
                            'categoria' => [
                                'type' => 'string',
                                'description' => 'Categoría favorita: celulares, laptops, wearables, electro cocina o linea blanca.',
                            ],
                            'ciudad' => [
                                'type' => 'integer',
                                'enum' => [1, 2, 3],
                                'description' => 'City Tier (1, 2 o 3).',
                            ],
                            'limite' => [
                                'type' => 'integer',
                                'description' => 'Cuántos clientes devolver (máximo 50).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_cliente',
                    'description' => 'Busca un cliente por nombre y devuelve su riesgo de abandono (probabilidad, nivel y acción sugerida) más sus datos clave. Úsalo para "¿este cliente se va a ir?", "dame el riesgo de X".',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nombre' => [
                                'type' => 'string',
                                'description' => 'Nombre (o parte del nombre) del cliente a buscar.',
                            ],
                        ],
                        'required' => ['nombre'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'stats_por_categoria',
                    'description' => 'Estadísticas de riesgo agrupadas por categoría favorita: total de clientes, cuántos en riesgo y probabilidad media. Úsalo para "qué categoría tiene más riesgo".',
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'stats_por_ciudad',
                    'description' => 'Estadísticas de riesgo agrupadas por ciudad (City Tier): total, en riesgo y probabilidad media. Úsalo para "qué ciudad tiene más riesgo".',
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
        ];
    }

    /** Despacha la llamada del modelo a la función real. Devuelve un array serializable. */
    public function ejecutar(string $nombre, array $args): array
    {
        return match ($nombre) {
            'resumen_riesgo'      => $this->resumenRiesgo(),
            'clientes_en_riesgo'  => $this->clientesEnRiesgo($args),
            'buscar_cliente'      => $this->buscarCliente($args['nombre'] ?? ''),
            'stats_por_categoria' => $this->statsPorCategoria(),
            'stats_por_ciudad'    => $this->statsPorCiudad(),
            default               => ['error' => "Herramienta desconocida: {$nombre}"],
        };
    }

    // ---------------------------------------------------------------------

    private function resumenRiesgo(): array
    {
        $porNivel = Customer::whereNotNull('churn_level')
            ->selectRaw('churn_level, COUNT(*) n')
            ->groupBy('churn_level')
            ->pluck('n', 'churn_level');

        return [
            'total_clientes' => Customer::count(),
            'por_nivel' => [
                'alto'     => (int) ($porNivel['alto'] ?? 0),
                'medio'    => (int) ($porNivel['medio'] ?? 0),
                'moderado' => (int) ($porNivel['moderado'] ?? 0),
                'bajo'     => (int) ($porNivel['bajo'] ?? 0),
            ],
            'nota' => 'alto >= 70% prob., medio 40-69%, moderado 20-39%, bajo < 20%.',
        ];
    }

    private function clientesEnRiesgo(array $args): array
    {
        $nivel  = $args['nivel'] ?? null;
        $ciudad = $args['ciudad'] ?? null;
        $limite = min((int) ($args['limite'] ?? 10), self::LIMITE_MAX);
        $cat    = $this->normalizarCategoria($args['categoria'] ?? null);

        $q = Customer::query()
            ->whereNotNull('churn_probability')
            ->when($nivel, fn ($q) => $q->where('churn_level', $nivel))
            ->when(!$nivel, fn ($q) => $q->whereIn('churn_level', ['alto', 'medio', 'moderado']))
            ->when($cat, fn ($q) => $q->where('prefered_order_cat', $cat))
            ->when($ciudad, fn ($q) => $q->where('city_tier', $ciudad))
            ->orderByDesc('churn_probability')
            ->limit($limite);

        $clientes = $q->get()->map(fn ($c) => [
            'id'          => $c->id,
            'nombre'      => $c->name,
            'probabilidad'=> round($c->churn_probability * 100, 1) . '%',
            'nivel'       => $c->churn_level,
            'categoria'   => self::CATEGORIAS[$c->prefered_order_cat] ?? $c->prefered_order_cat,
            'ciudad_tier' => $c->city_tier,
            'accion'      => $c->accionSugerida(),
        ]);

        return [
            'filtros'   => array_filter(['nivel' => $nivel, 'categoria' => $cat, 'ciudad' => $ciudad]),
            'devueltos' => $clientes->count(),
            'clientes'  => $clientes,
        ];
    }

    private function buscarCliente(string $nombre): array
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return ['error' => 'Debes indicar un nombre.'];
        }

        $clientes = Customer::where('name', 'like', "%{$nombre}%")
            ->whereNotNull('churn_probability')
            ->orderByDesc('churn_probability')
            ->limit(5)
            ->get();

        if ($clientes->isEmpty()) {
            return ['encontrados' => 0, 'mensaje' => "No se encontró ningún cliente que coincida con \"{$nombre}\"."];
        }

        return [
            'encontrados' => $clientes->count(),
            'clientes' => $clientes->map(fn ($c) => [
                'id'                 => $c->id,
                'nombre'             => $c->name,
                'probabilidad_churn' => round($c->churn_probability * 100, 1) . '%',
                'nivel'              => $c->churn_level,
                'accion_sugerida'    => $c->accionSugerida(),
                'categoria_favorita' => self::CATEGORIAS[$c->prefered_order_cat] ?? $c->prefered_order_cat,
                'ciudad_tier'        => $c->city_tier,
                'antiguedad_meses'   => $c->tenure,
                'satisfaccion'       => $c->satisfaction_score,
                'puso_queja'         => $c->complain ? 'sí' : 'no',
            ])->all(),
        ];
    }

    private function statsPorCategoria(): array
    {
        $rows = Customer::whereNotNull('prefered_order_cat')
            ->selectRaw("prefered_order_cat,
                COUNT(*) total,
                SUM(CASE WHEN churn_level IN ('alto','medio') THEN 1 ELSE 0 END) en_riesgo,
                ROUND(AVG(churn_probability)*100,1) prob_media")
            ->groupBy('prefered_order_cat')
            ->orderByDesc('en_riesgo')
            ->get();

        return ['por_categoria' => $rows->map(fn ($r) => [
            'categoria'  => self::CATEGORIAS[$r->prefered_order_cat] ?? $r->prefered_order_cat,
            'total'      => (int) $r->total,
            'en_riesgo'  => (int) $r->en_riesgo,
            'prob_media' => $r->prob_media . '%',
        ])->all()];
    }

    private function statsPorCiudad(): array
    {
        $rows = Customer::whereNotNull('city_tier')
            ->selectRaw("city_tier,
                COUNT(*) total,
                SUM(CASE WHEN churn_level IN ('alto','medio') THEN 1 ELSE 0 END) en_riesgo,
                ROUND(AVG(churn_probability)*100,1) prob_media")
            ->groupBy('city_tier')
            ->orderBy('city_tier')
            ->get();

        return ['por_ciudad' => $rows->map(fn ($r) => [
            'city_tier'  => (int) $r->city_tier,
            'total'      => (int) $r->total,
            'en_riesgo'  => (int) $r->en_riesgo,
            'prob_media' => $r->prob_media . '%',
        ])->all()];
    }

    /** Mapea términos en español a los valores del dataset. */
    private function normalizarCategoria(?string $cat): ?string
    {
        if (!$cat) {
            return null;
        }
        $c = mb_strtolower(trim($cat));
        return match (true) {
            str_contains($c, 'celular') || str_contains($c, 'movil') || str_contains($c, 'phone') => 'Mobile Phone',
            str_contains($c, 'laptop') || str_contains($c, 'compu')                               => 'Laptop & Accessory',
            str_contains($c, 'wearable') || str_contains($c, 'fashion') || str_contains($c, 'reloj') => 'Fashion',
            str_contains($c, 'cocina') || str_contains($c, 'grocery')                              => 'Grocery',
            str_contains($c, 'linea') || str_contains($c, 'blanca') || str_contains($c, 'other')   => 'Others',
            default => null,
        };
    }
}
