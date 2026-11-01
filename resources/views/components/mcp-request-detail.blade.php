<?php

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Traits\NoerdDetail;
use NoerdMcp\Models\McpRequest;
use NoerdMcp\Support\McpServerRegistry;

/**
 * One request of the MCP access log, read-only: the log is written by the
 * application alone and never edited or deleted from the UI. Admins only.
 */
new class extends Component {
    use NoerdDetail;

    public $detailModel = McpRequest::class;

    public ?string $detailPrimary = 'mcpRequestId';

    public string $argumentsJson = '';

    protected bool $deletable = false;

    public function mount(): void
    {
        abort_unless((bool) NoerdAuth::user()?->isAdmin(), 403);

        $this->initDetail();
    }

    /** The log is written by the application alone. */
    public function store(): void {}

    public function canSaveObject(): bool
    {
        return false;
    }

    protected function detailLoaded(Model $request): void
    {
        $this->detailData['user_name'] = $request->user?->name ?? '';
        $this->detailData['server_label'] = __(app(McpServerRegistry::class)->find((string) $request->server)?->label ?? (string) $request->server);
        $this->detailData['status_label'] = $request->status?->label() ?? '';

        $this->argumentsJson = $request->arguments === null
            ? ''
            : (string) json_encode($request->arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('MCP Request') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />

    @if ($argumentsJson !== '')
        <div class="mt-6">
            <h3 class="text-sm font-semibold text-zinc-900">{{ __('Arguments') }}</h3>
            <pre class="mt-2 max-h-96 overflow-auto rounded-md border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-800">{{ $argumentsJson }}</pre>
        </div>
    @endif
</x-noerd::page>
