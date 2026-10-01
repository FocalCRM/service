<?php

declare(strict_types=1);

// Dependency rules scan source text; see sourceFilesMatching() in tests/Pest.php.
it('service domain remains strictly headless (no Filament or Livewire)', function (): void {
    expect(sourceFilesMatching('/(?<![\\\\\w])(Filament|Livewire)\\\\+[A-Z]/'))->toBeEmpty();
});

it('service does not depend on sales, marketing, or the Filament UI', function (): void {
    expect(sourceFilesMatching('/\bFocal\\\\+(Sales|Marketing|Filament)\\\\+/'))->toBeEmpty();
});

arch('no debug functions are left in the code')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('all service domain actions have an execute method')
    ->expect('Focal\Service\Actions')
    ->toHaveMethod('execute');

arch('all service enums are string backed for database agnosticism')
    ->expect('Focal\Service\Enums')
    ->toBeStringBackedEnums();
