<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigins = [
            'https://tools.aoseudispor.com.br',
            'http://localhost:3000',
            'http://localhost:3001',
            'http://127.0.0.1:3000',
        ];

        // Determina a origem permitida
        $allowedOrigin = in_array($origin, $allowedOrigins, true) ? $origin : '*';

        // Headers CORS padrão
        $corsHeaders = [
            'Access-Control-Allow-Origin' => $allowedOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ];

        // Responde a requisições OPTIONS (preflight)
        if ($request->isMethod('OPTIONS')) {
            return response('', 200, $corsHeaders);
        }

        $response = $next($request);

        // Adiciona headers CORS em todas as respostas de rotas API
        if ($request->is('api/*') || $request->is('api')) {
            foreach ($corsHeaders as $key => $value) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}

