<?php

declare(strict_types=1);

namespace ReturnEarly\ActionsPattern\Traits;

trait ActionsPattern
{
    use Fakeable;

    public static function make(): static
    {
        return app(static::class);
    }
}
