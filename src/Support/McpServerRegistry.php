<?php

declare(strict_types=1);

namespace NoerdMcp\Support;

use InvalidArgumentException;
use Laravel\Mcp\Server;

/**
 * The MCP servers of the installation. A module registers its server in its
 * provider's boot(); this package serves every registered server at
 * `/mcp/{key}` behind OAuth, the tenant approval and the access log. The
 * registry starts empty — the package knows no server of its own.
 */
final class McpServerRegistry
{
    /** @var array<string, McpServerDefinition> */
    private array $servers = [];

    /**
     * @param  class-string<Server>  $serverClass
     * @param  string|list<string>  $apps  the tenant apps that give access (one is enough)
     */
    public function register(string $key, string $serverClass, string|array $apps, string $label, ?string $description = null): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $key) !== 1) {
            throw new InvalidArgumentException("The MCP server key [{$key}] must be kebab-case.");
        }

        $this->servers[$key] = new McpServerDefinition(
            key: $key,
            serverClass: $serverClass,
            apps: array_values((array) $apps),
            label: $label,
            description: $description,
        );
    }

    public function find(string $key): ?McpServerDefinition
    {
        return $this->servers[$key] ?? null;
    }

    /**
     * @return array<string, McpServerDefinition>
     */
    public function all(): array
    {
        return $this->servers;
    }
}
