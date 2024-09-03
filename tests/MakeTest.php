<?php

declare(strict_types=1);

use ReturnEarly\ActionsPattern\Commands\MakeActionCommand;

it('can make a new action', function (): void {
    $this->artisan(MakeActionCommand::class, ['name' => 'TestAction'])
        ->assertExitCode(0);

    $this->assertFileExists(app_path('Actions/TestAction.php'));
});

it('can make a new action with a custom namespace', function (): void {
    $this->artisan(MakeActionCommand::class, ['name' => 'Custom\\TestAction'])
        ->assertExitCode(0);

    $this->assertFileExists(app_path('Actions/Custom/TestAction.php'));
});
