<?php

declare(strict_types=1);

arch('service domain remains strictly headless')
    ->expect('Focal\Service')
    ->not->toUse([
        'Filament',
        'Livewire',
    ]);

arch('no debug functions left in service code')
    ->expect('Focal\Service')
    ->not->toUse([
        'dd',
        'dump',
        'ray',
        'var_dump',
    ]);

arch('all service domain actions have an execute method')
    ->expect('Focal\Service\Actions')
    ->toHaveMethod('execute');

arch('all service enums are string backed for database agnosticism')
    ->expect('Focal\Service\Enums')
    ->toBeStringBackedEnums();
