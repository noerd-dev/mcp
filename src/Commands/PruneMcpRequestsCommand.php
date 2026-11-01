<?php

declare(strict_types=1);

namespace NoerdMcp\Commands;

use Illuminate\Console\Command;
use NoerdMcp\Models\McpRequest;

/**
 * Deletes access log rows older than the retention, for every tenant. The
 * module schedules nothing — the host does.
 */
class PruneMcpRequestsCommand extends Command
{
    protected $signature = 'noerd-mcp:prune-requests {--days= : Retention in days (default config noerd-mcp.log.retention_days)}';

    protected $description = 'Delete MCP access log entries older than the retention';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('noerd-mcp.log.retention_days', 90));

        $deleted = McpRequest::query()
            ->acrossTenants()
            ->where('created_at', '<', now()->subDays(max(1, $days)))
            ->delete();

        $this->info("Deleted {$deleted} MCP access log entries older than {$days} days.");

        return self::SUCCESS;
    }
}
