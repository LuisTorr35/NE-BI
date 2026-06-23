<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GroqService;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    /** Cuántos turnos previos se recuerdan (para que el contexto no crezca sin control). */
    private const MAX_HISTORIAL = 8;

    public function index(GroqService $groq)
    {
        return view('admin.asistente.index', [
            'configurado' => $groq->configurado(),
        ]);
    }

    public function preguntar(Request $request, GroqService $groq)
    {
        $data = $request->validate([
            'mensaje' => ['required', 'string', 'max:500'],
        ]);

        if (!$groq->configurado()) {
            return response()->json([
                'ok'    => false,
                'error' => 'El chatbot no está configurado (falta GROQ_API_KEY en .env).',
            ], 422);
        }

        // Historial corto guardado en sesión (solo roles user/assistant en texto).
        $historial = session('chatbot_historial', []);

        try {
            $res = $groq->preguntar($data['mensaje'], $historial);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => 'No se pudo contactar al servicio de IA. ' . $e->getMessage(),
            ], 502);
        }

        // Actualizar historial (recortado).
        $historial[] = ['role' => 'user', 'content' => $data['mensaje']];
        $historial[] = ['role' => 'assistant', 'content' => $res['respuesta']];
        session(['chatbot_historial' => array_slice($historial, -self::MAX_HISTORIAL * 2)]);

        return response()->json([
            'ok'           => true,
            'respuesta'    => $res['respuesta'],
            'herramientas' => $res['herramientas'],
        ]);
    }

    public function limpiar(Request $request)
    {
        $request->session()->forget('chatbot_historial');
        return response()->json(['ok' => true]);
    }
}
