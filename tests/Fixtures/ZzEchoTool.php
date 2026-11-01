<?php

declare(strict_types=1);

namespace NoerdMcp\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use NoerdMcp\Tools\Concerns\ActsForNoerdUser;

/**
 * Answers the tenant it runs in and the text it got.
 */
class ZzEchoTool extends Tool
{
    use ActsForNoerdUser;

    protected string $name = 'zz_echo';

    protected string $description = 'Echoes the text and the tenant.';

    public function handle(Request $request): Response
    {
        return $this->answer(['tenant_id' => $this->tenantId(), 'text' => (string) $request->get('text')]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['text' => $schema->string()];
    }
}
