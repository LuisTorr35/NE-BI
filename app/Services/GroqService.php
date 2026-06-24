<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Cliente del chatbot BI sobre Groq (API compatible con OpenAI).
 *
 * Orquesta el ciclo de "function calling":
 *   1. Manda la conversación + las herramientas disponibles a Llama.
 *   2. Si Llama pide una herramienta, la ejecuta con BiTools (datos reales).
 *   3. Devuelve los resultados a Llama para que redacte la respuesta final.
 * Repite el paso 2-3 hasta que Llama responde texto (máx. unas pocas vueltas).
 */
class GroqService
{
    private string $key;
    private string $model;
    private string $baseUrl;

    public function __construct(private BiTools $tools)
    {
        $this->key     = (string) config('services.groq.key');
        $this->model   = (string) config('services.groq.model');
        $this->baseUrl = rtrim((string) config('services.groq.base_url'), '/');
    }

    public function configurado(): bool
    {
        return $this->key !== '';
    }

    /**
     * Procesa la pregunta del usuario dentro de un historial y devuelve la respuesta.
     *
     * @param  array  $historial  Mensajes previos [{role, content}] (sin el system).
     * @return array{respuesta:string, herramientas:array<string>}
     */
    public function preguntar(string $mensaje, array $historial = []): array
    {
        $mensajes = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt()]],
            $historial,
            [['role' => 'user', 'content' => $mensaje]],
        );

        $herramientasUsadas = [];

        // Máximo 4 vueltas para evitar bucles infinitos de tool-calling.
        for ($vuelta = 0; $vuelta < 4; $vuelta++) {
            $resp = Http::withToken($this->key)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model'       => $this->model,
                    'messages'    => $mensajes,
                    'tools'       => BiTools::definiciones(),
                    'tool_choice' => 'auto',
                    'temperature' => 0.2,
                ]);

            if ($resp->failed()) {
                throw new \RuntimeException('Groq respondió ' . $resp->status() . ': ' . $resp->body());
            }

            $msg = $resp->json('choices.0.message');

            // ¿Llama pidió ejecutar herramientas?
            if (!empty($msg['tool_calls'])) {
                $mensajes[] = $msg; // el turno del asistente con las tool_calls

                foreach ($msg['tool_calls'] as $call) {
                    $fn   = $call['function']['name'] ?? '';
                    $args = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];
                    $herramientasUsadas[] = $fn;

                    $resultado = $this->tools->ejecutar($fn, $args);

                    $mensajes[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $call['id'],
                        'name'         => $fn,
                        'content'      => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                    ];
                }
                continue; // volver a preguntar a Llama con los datos ya cargados
            }

            // Respuesta final en texto
            return [
                'respuesta'    => trim($msg['content'] ?? 'No pude generar una respuesta.'),
                'herramientas' => array_values(array_unique($herramientasUsadas)),
            ];
        }

        return [
            'respuesta'    => 'La consulta fue demasiado compleja. Intenta reformularla.',
            'herramientas' => array_values(array_unique($herramientasUsadas)),
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        Eres el "Asistente BI" de SOLE, una tienda de electrodomésticos y tecnología.
        Tu ÚNICO tema es la predicción de abandono (churn) de los clientes de SOLE y las
        estadísticas de riesgo. Respondes siempre en español, de forma breve y clara.

        REGLAS ESTRICTAS:
        - NUNCA inventes clientes, números, probabilidades ni nombres. Usa SOLO los datos
          que devuelven las herramientas. Si una herramienta no trae resultados, dilo.
        - Para cualquier dato concreto (quiénes están en riesgo, el riesgo de un cliente,
          conteos, estadísticas) DEBES llamar a una herramienta. No respondas de memoria.
        - Habla de "probabilidad de abandono", no de certezas. Ej: "tiene 99.7% de
          probabilidad de abandonar (riesgo alto)".
        - Cuando la pregunta sea sobre porcentajes (qué % está en riesgo, en cada nivel,
          por categoría o ciudad), USA los porcentajes que ya vienen en los resultados de
          las herramientas (campos como por_nivel_pct, en_riesgo_pct, pct_en_riesgo). NO
          los calcules tú a mano: reporta el valor exacto que devolvió la herramienta.
        - Cuando muestres una lista de clientes, usa viñetas con nombre, probabilidad y nivel.
        - Si te preguntan algo fuera de este tema (no relacionado con churn/clientes de SOLE),
          responde amablemente que solo puedes ayudar con el análisis de riesgo de abandono.
        - Cuando sea útil, menciona la acción de retención sugerida según el nivel
          (alto: cupón inmediato; medio: correo personalizado; moderado: remarketing).
        PROMPT;
    }
}
