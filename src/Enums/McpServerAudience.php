<?php

declare(strict_types=1);

namespace NoerdMcp\Enums;

/**
 * Who of a tenant may use an MCP server an admin allowed: every person of the
 * company, or only the people the admin selected (tenant admins always may).
 */
enum McpServerAudience: string
{
    case All = 'all';
    case SelectedUsers = 'selected';
}
