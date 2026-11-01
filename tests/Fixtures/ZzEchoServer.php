<?php

declare(strict_types=1);

namespace NoerdMcp\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * A synthetic MCP server for the package's tests — never a business module.
 */
#[Name('Zz Echo')]
#[Version('1.0.0')]
class ZzEchoServer extends Server
{
    protected array $tools = [
        ZzEchoTool::class,
        ZzFailTool::class,
    ];
}
