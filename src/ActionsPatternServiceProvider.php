<?php

declare(strict_types=1);

namespace ReturnEarly\ActionsPattern;

use ReturnEarly\ActionsPattern\Commands\MakeActionCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ActionsPatternServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('actions-pattern')
            ->hasCommand(MakeActionCommand::class);
    }
}
