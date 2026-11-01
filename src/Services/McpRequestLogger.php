<?php

declare(strict_types=1);

namespace NoerdMcp\Services;

use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;
use Noerd\Support\TenantContext;
use NoerdMcp\Enums\McpRequestStatus;
use NoerdMcp\Http\Middleware\ActAsNoerdUser;
use NoerdMcp\Models\McpRequest;
use NoerdMcp\Models\McpUser;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The ONE place an MCP access is written to `mcp_requests`: who (user, tenant,
 * OAuth client), what (server, JSON-RPC method, tool and its arguments — cut
 * to `noerd-mcp.log.max_argument_bytes`), how it ended (ok, error, refused)
 * and how long it took. Notifications and pings carry nothing worth keeping
 * and are skipped. Logging never breaks a request: a failure is reported.
 */
class McpRequestLogger
{
    public function record(Request $request, Response $response, string $server, int $durationMs): void
    {
        try {
            $payload = $request->json()->all();

            if (array_is_list($payload) && isset($payload[0]) && is_array($payload[0])) {
                $payload = $payload[0];
            }

            $method = is_string($payload['method'] ?? null) ? $payload['method'] : null;

            if ($method === 'ping' || ($method !== null && str_starts_with($method, 'notifications/'))) {
                return;
            }

            $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
            [$status, $error] = $this->outcome($response);
            $tenantId = $request->attributes->get(ActAsNoerdUser::TENANT_ATTRIBUTE);
            [$clientId, $clientName] = $this->client($request);

            $attributes = [
                'tenant_id' => $tenantId,
                'user_id' => $request->attributes->get(ActAsNoerdUser::USER_ATTRIBUTE) ?? $request->user()?->getAuthIdentifier(),
                'server' => $server,
                'client_id' => $clientId,
                'client_name' => $clientName,
                'method' => $method === null ? null : mb_substr($method, 0, 100),
                'tool' => $method === 'tools/call' && is_string($params['name'] ?? null) ? mb_substr($params['name'], 0, 255) : null,
                'arguments' => $method === 'tools/call' ? $this->arguments($params['arguments'] ?? null) : null,
                'status' => $status,
                'http_status' => $response->getStatusCode(),
                'error' => $error === null ? null : mb_substr($error, 0, 2000),
                'duration_ms' => max(0, $durationMs),
                'ip' => $request->ip(),
            ];

            $tenantId === null
                ? TenantContext::system(fn() => McpRequest::create($attributes))
                : TenantContext::run((int) $tenantId, fn() => McpRequest::create($attributes));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array{0: McpRequestStatus, 1: ?string}
     */
    private function outcome(Response $response): array
    {
        $content = $response->getContent();
        $decoded = is_string($content) && $content !== '' ? json_decode($content, true) : null;
        $decoded = is_array($decoded) ? $decoded : [];

        if ($response->getStatusCode() >= 400) {
            $message = $decoded['message'] ?? $decoded['error']['message'] ?? null;

            return [
                in_array($response->getStatusCode(), [401, 403, 404], true) ? McpRequestStatus::Refused : McpRequestStatus::Error,
                is_string($message) ? $message : null,
            ];
        }

        if (is_array($decoded['error'] ?? null)) {
            return [McpRequestStatus::Error, is_string($decoded['error']['message'] ?? null) ? $decoded['error']['message'] : null];
        }

        if (($decoded['result']['isError'] ?? false) === true) {
            $text = $decoded['result']['content'][0]['text'] ?? null;

            return [McpRequestStatus::Error, is_string($text) ? $text : null];
        }

        return [McpRequestStatus::Ok, null];
    }

    /**
     * The tool arguments, or — beyond the size limit — the beginning of their
     * JSON marked as truncated.
     *
     * @return array<string, mixed>|null
     */
    private function arguments(mixed $arguments): ?array
    {
        if (! is_array($arguments) || $arguments === []) {
            return null;
        }

        $json = (string) json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $limit = max(256, (int) config('noerd-mcp.log.max_argument_bytes', 4000));

        if (mb_strlen($json) <= $limit) {
            return $arguments;
        }

        return ['_truncated' => true, '_preview' => mb_substr($json, 0, $limit)];
    }

    /**
     * The OAuth client (e.g. Claude) whose access token the request carries.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function client(Request $request): array
    {
        $user = $request->user();
        $token = $user instanceof McpUser ? $user->token() : null;
        $clientId = $token instanceof AccessToken ? $token->oauth_client_id : null;

        if (! is_string($clientId) || $clientId === '') {
            return [null, null];
        }

        $name = Passport::clientModel()::query()->whereKey($clientId)->value('name');

        return [mb_substr($clientId, 0, 100), is_string($name) ? mb_substr($name, 0, 255) : null];
    }
}
