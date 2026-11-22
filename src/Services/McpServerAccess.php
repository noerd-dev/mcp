<?php

declare(strict_types=1);

namespace NoerdMcp\Services;

use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;
use NoerdMcp\Enums\McpServerAudience;
use NoerdMcp\Models\McpServerSetting;
use NoerdMcp\Support\McpServerDefinition;
use NoerdMcp\Support\McpServerRegistry;

/**
 * Which registered MCP servers a person may use in a tenant: one of the
 * server's apps is usable there, an admin allowed the server AND the server is
 * open to everybody or the admin selected the person (tenant admins always
 * may). The SAME rule as {@see \NoerdMcp\Http\Middleware\ActAsNoerdUser} —
 * the screens ask here, the routes enforce it there.
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

    public function audience(int $tenantId, McpServerDefinition $definition): McpServerAudience
    {
        return McpServerSetting::audienceFor($tenantId, $definition->key);
    }

    /**
     * Whether the server's audience lets the person in — not whether the
     * server is allowed at all ({@see enabled()}).
     */
    public function allowsUser(int $tenantId, McpServerDefinition $definition, NoerdUser $user): bool
    {
        if ($this->audience($tenantId, $definition) === McpServerAudience::All) {
            return true;
        }

        return $user->isAdmin($tenantId)
            || McpServerSetting::isUserAllowed($tenantId, $definition->key, (int) $user->getKey());
    }

    /**
     * The servers the signed-in person may use in the tenant.
     *
     * @return list<McpServerDefinition>
     */
    public function availableFor(int $tenantId): array
    {
        $user = NoerdAuth::user();

        if (! $user instanceof NoerdUser) {
            return [];
        }

        return array_values(array_filter(
            $this->registry->all(),
            fn(McpServerDefinition $definition): bool => $this->appUsable($definition)
                && $this->enabled($tenantId, $definition)
                && $this->allowsUser($tenantId, $definition, $user),
        ));
    }
}
