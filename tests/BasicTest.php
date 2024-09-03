<?php

declare(strict_types=1);

use ReturnEarly\ActionsPattern\Tests\Examples\BasicAction;

it('can get a new instance of the action', function (): void {
    $action = BasicAction::make();

    expect($action)->toBeInstanceOf(BasicAction::class);
});

it('can call handle', function (): void {
    expect(BasicAction::make()->handle('Goodbye'))->toBe('Goodbye');
});

it('can be mocked', function (): void {
    $mock = BasicAction::mock();

    $mock->shouldReceive('handle')->andReturn('Hello');

    expect($mock->handle('Goodbye'))->toBe('Hello');
});
