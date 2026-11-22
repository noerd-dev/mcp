<?php

declare(strict_types=1);

namespace NoerdMcp\Models;

use Illuminate\Database\Eloquent\Model;
use Noerd\Traits\BelongsToTenant;

/**
 * A person a tenant admin selected for an MCP server (`server` = registry key)
 * whose audience is {@see \NoerdMcp\Enums\McpServerAudience::SelectedUsers}.
 * Written only through {@see McpServerSetting::setUserAllowed()}.
 */
class McpServerUser extends Model
{
    use BelongsToTenant;

    protected $table = 'mcp_server_users';

    protected $guarded = [];
}
