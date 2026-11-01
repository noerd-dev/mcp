<?php

declare(strict_types=1);

namespace NoerdMcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use NoerdMcp\Services\McpRequestLogger;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs every MCP request of an authenticated person — answered or refused —
 * through {@see McpRequestLogger}. Goes after {@see AuthenticateMcpUser} (a
 * client probing without a token is not a person's access) and before
 * {@see ActAsNoerdUser}, so its refusals are logged too.
 */
class LogMcpRequest
{
    public function __construct(private readonly McpRequestLogger $logger) {}

    public function handle(Request $request, Closure $next, string $server): Response
    {
        $startedAt = hrtime(true);

        $response = $next($request);

        $this->logger->record($request, $response, $server, (int) round((hrtime(true) - $startedAt) / 1_000_000));

        return $response;
    }
}
