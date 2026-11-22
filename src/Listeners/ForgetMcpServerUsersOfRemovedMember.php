<?php

declare(strict_types=1);

namespace NoerdMcp\Listeners;

use Noerd\Events\TenantMemberRemoved;
use NoerdMcp\Models\McpServerUser;

/**
 * A person who leaves a tenant loses the MCP servers an admin selected them
 * for there, so a later membership starts clean. A deleted account goes with
 * the foreign key.
 */
class ForgetMcpServerUsersOfRemovedMember
{
    public function handle(TenantMemberRemoved $event): void
    {
        McpServerUser::query()->ofTenant($event->tenantId)->where('user_id', $event->userId)->delete();
    }
}
