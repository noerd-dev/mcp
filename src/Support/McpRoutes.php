<?php

declare(strict_types=1);

namespace NoerdMcp\Support;

use Illuminate\Routing\Route;
use Laravel\Mcp\Facades\Mcp;
use NoerdMcp\Http\Middleware\ActAsNoerdUser;
use NoerdMcp\Http\Middleware\AuthenticateMcpUser;
use NoerdMcp\Http\Middleware\LogMcpRequest;

/**
 * The route of one registered MCP server, `/mcp/{key}`, with the middleware
 * every server shares: OAuth token → access log → the person in their tenant,
 * app check and tenant approval. The ONE place this chain is built — the
 * provider registers every server through it.
 */
final class McpRoutes
{
    public static function server(McpServerDefinition $definition): Route
    {
        return Mcp::web($definition->path(), $definition->serverClass)
            ->middleware([
                AuthenticateMcpUser::class,
                LogMcpRequest::class . ':' . $definition->key,
                ActAsNoerdUser::class . ':' . $definition->key,
            ])
            ->name('noerd-mcp.server.' . $definition->key);
    }
}
