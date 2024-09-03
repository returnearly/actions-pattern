<?php

declare(strict_types=1);

namespace ReturnEarly\ActionsPattern\Traits;

use Mockery;
use Mockery\Expectation;
use Mockery\ExpectationInterface;
use Mockery\HigherOrderMessage;
use Mockery\MockInterface;

trait Fakeable
{
    public static function mock(): MockInterface
    {
        if (static::isFake()) {
            return static::getFakeResolvedInstance();
        }

        $mock = Mockery::mock(static::class);
        $mock->shouldAllowMockingProtectedMethods();

        return static::setFakeResolvedInstance($mock);
    }

    public static function spy(): MockInterface
    {
        if (static::isFake()) {
            return static::getFakeResolvedInstance();
        }

        return static::setFakeResolvedInstance(Mockery::spy(static::class));
    }

    public static function partialMock(): MockInterface
    {
        return static::mock()->makePartial();
    }

    /**
     * @return Expectation|ExpectationInterface|HigherOrderMessage
     */
    public static function shouldRun()
    {
        return static::mock()->shouldReceive('handle');
    }

    /**
     * @return Expectation|ExpectationInterface|HigherOrderMessage
     */
    public static function shouldNotRun()
    {
        return static::mock()->shouldNotReceive('handle');
    }

    /**
     * @return Expectation|ExpectationInterface|HigherOrderMessage|MockInterface
     */
    public static function allowToRun()
    {
        return static::spy()->allows('handle');
    }

    public static function isFake(): bool
    {
        return app()->isShared(static::getFakeResolvedInstanceKey());
    }

    public static function clearFake(): void
    {
        app()->forgetInstance(static::getFakeResolvedInstanceKey());
    }

    protected static function setFakeResolvedInstance(MockInterface $fake): MockInterface
    {
        return app()->instance(static::getFakeResolvedInstanceKey(), $fake);
    }

    protected static function getFakeResolvedInstance(): ?MockInterface
    {
        return app(static::getFakeResolvedInstanceKey());
    }

    protected static function getFakeResolvedInstanceKey(): string
    {
        return 'ActionsPattern:AsFake:' . static::class;
    }
}
