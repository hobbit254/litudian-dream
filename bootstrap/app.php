<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            ForceJsonResponse::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            // This is equivalent to $this->renderable() in the old Handler.php
            if ($request->expectsJson()) {

                // Collect all error messages and flatten them into a simple array
                $errors = collect($e->errors())->flatten()->toArray();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation Failed. Please check the provided data.',
                    'details' => $errors,
                ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY); // HTTP 422
            }
        });

    })->create();
