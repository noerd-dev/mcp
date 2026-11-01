<?php

use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Traits\NoerdList;
use NoerdMcp\Models\McpRequest;
use NoerdMcp\Support\McpServerRegistry;

/**
 * The MCP access log of the selected tenant — admins only (registered with
 * the ComponentAccessGuard and checked in mount()). Rows open the read-only
 * request detail by route.
 */
new class extends Component {
    use NoerdList;

    public $listModel = McpRequest::class;

    public ?string $detailRoute = 'noerd-mcp.request.detail';

    public $detailComponent = 'noerd-mcp::mcp-request-detail';

    protected array $listWith = ['user'];

    public function mount(): void
    {
        abort_unless((bool) NoerdAuth::user()?->isAdmin(), 403);

        $this->mountList();
    }

    public function listData(): array
    {
        $registry = app(McpServerRegistry::class);
        $rows = $this->paginateList($this->listQuery($this->listModel)->orderByDesc('id'));

        $rows->getCollection()->transform(function (McpRequest $row) use ($registry): McpRequest {
            $row->user_name = $row->user?->name ?? '';
            $row->server_label = __($registry->find($row->server)?->label ?? $row->server);

            return $row;
        });

        return $this->buildList($rows);
    }
}; ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
