<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mcp_server_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('server', 100);
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'server']);
        });

        Schema::create('mcp_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('server', 100);
            $table->string('client_id', 100)->nullable();
            $table->string('client_name')->nullable();
            $table->string('method', 100)->nullable();
            $table->string('tool')->nullable();
            $table->json('arguments')->nullable();
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status');
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_requests');
        Schema::dropIfExists('mcp_server_settings');
    }
};
