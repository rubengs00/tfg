<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogFailedApiRequests
{

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->record($request, 500, class_basename($exception), $exception->getMessage());

            throw $exception;
        }

        if ($response->getStatusCode() >= 400) {
            $this->record($request, $response->getStatusCode(), $this->responseMessage($response));
        }

        return $response;
    }

    private function record(Request $request, int $status, ?string $message = null, ?string $exception = null): void
    {
        if (! Str::startsWith($request->path(), 'api/')) {
            return;
        }

        try {
            ActivityLog::query()->create([
                'user_id' => $request->user()?->id,
                'action' => 'api.request_failed',
                'resource_type' => 'api',
                'resource_id' => null,
                'metadata' => [
                    'method' => $request->method(),
                    'path' => '/' . $request->path(),
                    'status' => $status,
                    'message' => Str::limit((string) ($message ?: 'Peticion fallida'), 240),
                    'exception' => $exception,
                    'ip' => $request->ip(),
                ],
            ]);
        } catch (Throwable) {
        }
    }

    private function responseMessage(Response $response): string
    {
        if (! method_exists($response, 'getContent')) {
            return 'Peticion fallida';
        }

        $payload = json_decode((string) $response->getContent(), true);

        if (is_array($payload)) {
            if (isset($payload['message']) && is_string($payload['message'])) {
                return $payload['message'];
            }

            $firstError = collect($payload['errors'] ?? [])->flatten()->first();

            if (is_string($firstError)) {
                return $firstError;
            }
        }

        return 'Peticion fallida';
    }
}
