<?php

declare(strict_types=1);

namespace Thesis;

use Testo\Assert;
use Testo\Test;

final class DispatcherTest
{
    #[Test]
    public function callsHandlerOnDispatch(): void
    {
        $dispatcher = new Dispatcher();
        $called = false;

        $dispatcher->subscribe(\stdClass::class, static function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new \stdClass());

        Assert::true($called);
    }

    #[Test]
    public function callsMultipleHandlersInOrder(): void
    {
        $dispatcher = new Dispatcher();
        $order = [];

        $dispatcher->subscribe(\stdClass::class, static function () use (&$order): void {
            $order[] = 1;
        });
        $dispatcher->subscribe(\stdClass::class, static function () use (&$order): void {
            $order[] = 2;
        });

        $dispatcher->dispatch(new \stdClass());

        Assert::same($order, [1, 2]);
    }

    #[Test]
    public function passesHookToHandler(): void
    {
        $dispatcher = new Dispatcher();
        $hook = new \stdClass();
        $received = null;

        $dispatcher->subscribe(\stdClass::class, static function (\stdClass $h) use (&$received): void {
            $received = $h;
        });

        $dispatcher->dispatch($hook);

        Assert::same($received, $hook);
    }

    #[Test]
    public function doesNotCallHandlerForUnrelatedHook(): void
    {
        $dispatcher = new Dispatcher();
        $called = false;

        $dispatcher->subscribe(\stdClass::class, static function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new \ArrayObject());

        Assert::false($called);
    }

    #[Test]
    public function unsubscribeViaReturnedClosure(): void
    {
        $dispatcher = new Dispatcher();
        $called = false;

        $unsubscribe = $dispatcher->subscribe(\stdClass::class, static function () use (&$called): void {
            $called = true;
        });

        $unsubscribe();
        $dispatcher->dispatch(new \stdClass());

        Assert::false($called);
    }

    #[Test]
    public function unsubscribeDuringDispatch(): void
    {
        $dispatcher = new Dispatcher();
        $callCount = 0;

        $dispatcher->subscribe(\stdClass::class, static function (\stdClass $hook, \Closure $unsubscribe) use (&$callCount): void {
            ++$callCount;
            $unsubscribe();
        });

        $dispatcher->dispatch(new \stdClass());
        $dispatcher->dispatch(new \stdClass());

        Assert::same($callCount, 1);
    }

    #[Test]
    public function handlerAddedDuringDispatchIsCalledInSameDispatch(): void
    {
        $dispatcher = new Dispatcher();
        $order = [];

        $dispatcher->subscribe(\stdClass::class, static function () use ($dispatcher, &$order): void {
            $order[] = 1;

            $dispatcher->subscribe(\stdClass::class, static function () use (&$order): void {
                $order[] = 2;
            });
        });

        $dispatcher->dispatch(new \stdClass());

        Assert::same($order, [1, 2]);
    }

    #[Test]
    public function remainingHandlersAreCalledAfterPeerUnsubscribes(): void
    {
        $dispatcher = new Dispatcher();
        $secondCalled = false;

        $dispatcher->subscribe(\stdClass::class, static function (\stdClass $hook, \Closure $unsubscribe): void {
            $unsubscribe();
        });
        $dispatcher->subscribe(\stdClass::class, static function () use (&$secondCalled): void {
            $secondCalled = true;
        });

        $dispatcher->dispatch(new \stdClass());

        Assert::true($secondCalled);
    }
}
