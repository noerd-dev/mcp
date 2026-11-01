<?php

declare(strict_types=1);

namespace NoerdMcp\Services;

use Noerd\Helpers\AccessHelper;
use NoerdMcp\Models\McpServerSetting;
use NoerdMcp\Support\McpServerDefinition;
use NoerdMcp\Support\McpServerRegistry;

/**
 * Which registered MCP servers the signed-in person's selected tenant may use:
 * one of the server's apps is usable there AND an admin allowed the server.
 * The SAME rule as {@see \NoerdMcp\Http\Middleware\ActAsNoerdUser} — the
 * screens ask here, the routes enforce it there.
 */
class McpServerAccess
{
    public function __construct(private readonly McpServerRegistry $registry) {}

    public function appUsable(McpServerDefinition $definition): bool
    {
        return AccessHelper::canUseApp(...$definition->apps);
    }

    public function enabled(int $tenantId, McpServerDefinition $definition): bool
    {
        return McpServerSetting::isEnabled($tenantId, $definition->key);
    }

    /**
     * @return list<McpServerDefinition>
     */
    public function availableFor(int $tenantId): array
    {
        return array_values(array_filter(
            $this->registry->all(),
            fn(McpServerDefinition $definition): bool => $this->appUsable($definition) && $this->enabled($tenantId, $definition),
        ));
    }
}
