<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        
        // Aplica CORS primeiro nas rotas API
        $middleware->api(prepend: [
            \App\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Garante que headers CORS sejam adicionados mesmo em exceções
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                $origin = $request->headers->get('Origin');
                $allowedOrigins = [
                    'https://tools.aoseudispor.com.br',
                    'http://localhost:3000',
                    'http://localhost:3001',
                    'http://127.0.0.1:3000',
                ];
                $allowedOrigin = in_array($origin, $allowedOrigins, true) ? $origin : '*';
                
                $response = response()->json([
                    'error' => 'Erro interno do servidor',
                    'message' => config('app.debug') ? $e->getMessage() : 'Ocorreu um erro ao processar a requisição',
                ], 500);
                
                $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                
                return $response;
            }
        });
    })->create();
