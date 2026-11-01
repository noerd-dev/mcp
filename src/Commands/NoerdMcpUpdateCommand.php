<?php

declare(strict_types=1);

namespace NoerdMcp\Commands;

/**
 * Republishes the screen YAML and repeats the idempotent setup steps. Like
 * every update command it runs no migrations.
 */
class NoerdMcpUpdateCommand extends NoerdMcpInstallCommand
{
    protected $signature = 'noerd:update-mcp';

    protected $description = 'Update noerd/mcp';
}
