<?php

declare(strict_types=1);

namespace NoerdMcp\Support;

use Laravel\Mcp\Server;

/**
 * One MCP server a module contributes: its stable key (the URL segment
 * `/mcp/{key}` and the key of the tenant setting), the laravel/mcp server
 * class, the tenant apps that give access to it (one of them is enough) and
 * the label/description a person sees.
 */
final readonly class McpServerDefinition
{
    /**
     * @param  class-string<Server>  $serverClass
     * @param  list<string>  $apps
     */
    public function __construct(
        public string $key,
        public string $serverClass,
        public array $apps,
        public string $label,
        public ?string $description = null,
    ) {}

    public function path(): string
    {
        return 'mcp/' . $this->key;
    }

    public function url(): string
    {
        return url($this->path());
    }
}
