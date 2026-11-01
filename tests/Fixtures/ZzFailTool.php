<?php

declare(strict_types=1);

namespace NoerdMcp\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Always refuses — the error path of the access log.
 */
class ZzFailTool extends Tool
{
    protected string $name = 'zz_fail';

    protected string $description = 'Always refuses.';

    public function handle(Request $request): Response
    {
        return Response::error('Zz refused the request.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
