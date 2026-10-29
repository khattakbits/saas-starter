<?php

use App\Core\Exceptions\DocumentException;
use App\Core\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');

        // Deploy par, sirf agar app load balancer/proxy ke peeche hai (apni proxy ki IP/range likhein):
        // $middleware->trustProxies(at: ['10.0.0.0/8']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (DocumentException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        });
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            // fetch() wale JSON endpoints apna asli status code hi dein
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 419) {
                return back()->with('error', 'Your session expired. Please try again.');
            }

            // 403/404 hamesha; 500/503 sirf production mein (local par Laravel ka debug page)
            $custom = in_array($status, [403, 404], true)
                || (in_array($status, [500, 503], true) && ! app()->environment(['local', 'testing']));

            if ($custom) {
                return Inertia::render('Errors/Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
