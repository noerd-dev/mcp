<?php

declare(strict_types=1);

namespace NoerdMcp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Models\NoerdUser;
use Noerd\Traits\BelongsToTenant;
use NoerdMcp\Enums\McpRequestStatus;
use NoerdMcp\Services\McpRequestLogger;

/**
 * One MCP request an authenticated person sent — written ONLY by
 * {@see McpRequestLogger}. `arguments` are the tool arguments,
 * cut to a size limit; there is no `updated_at`.
 */
class McpRequest extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'mcp_requests';

    protected $guarded = [];

    protected $casts = [
        'arguments' => 'array',
        'status' => McpRequestStatus::class,
        'duration_ms' => 'int',
        'http_status' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(NoerdUser::class, 'user_id');
    }
}
