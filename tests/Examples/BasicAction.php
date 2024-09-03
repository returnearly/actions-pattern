<?php

declare(strict_types=1);

namespace ReturnEarly\ActionsPattern\Tests\Examples;

use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

class BasicAction implements ActionsPatternInterface
{
    use ActionsPattern;

    public function handle(string $message = 'Hello World!'): string
    {
        return $message;
    }
}
