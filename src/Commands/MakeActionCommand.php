<?php

declare(strict_types=1);

namespace ReturnEarly\ActionsPattern\Commands;

use Illuminate\Console\Concerns\CreatesMatchingTest;
use Illuminate\Console\GeneratorCommand;

class MakeActionCommand extends GeneratorCommand
{
    use CreatesMatchingTest;

    public $name = 'make:action';

    public $description = 'Create a new action class.';

    protected $type = 'Action';

    protected function getStub()
    {
        return __DIR__.'/../../stubs/action.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Actions";
    }
}
