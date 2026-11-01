<?php

declare(strict_types=1);

namespace NoerdMcp\Tools\Concerns;

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Response;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use NoerdMcp\Http\Middleware\ActAsNoerdUser;

/**
 * What every tool of an MCP server built on noerd/mcp shares: the tenant of
 * the acting noerd user (set by {@see ActAsNoerdUser}), the permission answers
 * and the answer shapes — JSON with plain decimals, a refusal as a translated
 * error, never an exception.
 */
trait ActsForNoerdUser
{
    protected function tenantId(): ?int
    {
        $tenantId = NoerdAuth::user()?->selected_tenant_id;

        return $tenantId ? (int) $tenantId : null;
    }

    protected function forbidden(): Response
    {
        return Response::error(__('You are not allowed to do this.'));
    }

    /**
     * @param  class-string  ...$modelClasses
     */
    protected function mayRead(string ...$modelClasses): bool
    {
        return $this->allows(AccessHelper::canReadObject(...), $modelClasses);
    }

    /**
     * @param  class-string  ...$modelClasses
     */
    protected function mayCreate(string ...$modelClasses): bool
    {
        return $this->allows(AccessHelper::canCreateObject(...), $modelClasses);
    }

    /**
     * @param  class-string  ...$modelClasses
     */
    protected function mayWrite(string ...$modelClasses): bool
    {
        return $this->allows(AccessHelper::canWriteObject(...), $modelClasses);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function answer(array $data): Response
    {
        return Response::json($data);
    }

    /**
     * An absolute link into the application, or null where the route does
     * not exist in this installation.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function link(string $name, array $parameters = []): ?string
    {
        return Route::has($name) ? route($name, $parameters) : null;
    }

    /**
     * @param  list<class-string>  $modelClasses
     */
    private function allows(callable $check, array $modelClasses): bool
    {
        foreach ($modelClasses as $modelClass) {
            if (! $check($modelClass)) {
                return false;
            }
        }

        return $this->tenantId() !== null;
    }
}
