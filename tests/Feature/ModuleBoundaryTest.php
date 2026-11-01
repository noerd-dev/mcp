<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/*
 | Architecture guard: every module the code uses is declared in composer.json,
 | and the package never names a business module. Allowances and forbidden
 | namespaces live in composer.json under extra.noerd.boundary.
 */
it('keeps its module boundary', function (): void {
    assertModuleBoundary(dirname(__DIR__, 2));
});
