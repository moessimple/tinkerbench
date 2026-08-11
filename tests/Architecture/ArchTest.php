<?php

declare(strict_types=1);

arch('domain does not depend on application')
    ->expect('Domain')
    ->not->toUse('Application');

arch('domain does not depend on the http/routing framework layer')
    ->expect('Domain')
    ->not->toUse(['Illuminate\Http', 'Illuminate\Routing']);

arch('business code classes are not final')
    ->expect(['Domain', 'Application', 'Support'])
    ->classes()
    ->not->toBeFinal();

arch('business code classes are not readonly')
    ->expect(['Domain', 'Application', 'Support'])
    ->classes()
    ->not->toBeReadonly();

arch('domain actions are suffixed correctly')
    ->expect('Domain\*\Actions')
    ->classes()
    ->toHaveSuffix('Action');

// expect('Application\*\...') can't be used here: pest-plugin-arch strips the trailing
// backslash off every registered PSR-4 prefix before matching, so "App" (Laravel's own
// namespace) matches as a false prefix of "Application" and Symfony Finder is handed a
// nonexistent path built from the wrong prefix. Domain and Support have no such collision.
it('suffixes application controllers correctly', function (): void {
    $root = dirname(__DIR__, 2);

    foreach (glob($root.'/src/Application/*/Controllers/*.php') as $file) {
        expect(basename($file, '.php'))->toEndWith('Controller');
    }
});

it('suffixes application requests correctly', function (): void {
    $root = dirname(__DIR__, 2);

    foreach (glob($root.'/src/Application/*/Requests/*.php') as $file) {
        expect(basename($file, '.php'))->toEndWith('Request');
    }
});
