<?php

declare(strict_types=1);

namespace Thesis;

use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Data\DataSet;
use Testo\Test;

final class DispatcherTest
{
    #[Test]
    public function callsHandlerOnDispatch(): void
    {
        $dispatcher = new Dispatcher();
        $called = false;

        $dispatcher->subscribe(HookA::class, static function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new HookA());

        Assert::true($called);
    }

    #[Test]
    public function callsMultipleHandlersInOrder(): void
    {
        $dispatcher = new Dispatcher();
        $order = [];

        $dispatcher->subscribe(HookA::class, static function () use (&$order): void {
            $order[] = 1;
        });
        $dispatcher->subscribe(HookA::class, static function () use (&$order): void {
            $order[] = 2;
        });

        $dispatcher->dispatch(new HookA());

        Assert::same($order, [1, 2]);
    }

    #[Test]
    public function passesHookToHandler(): void
    {
        $dispatcher = new Dispatcher();
        $hook = new HookA();
        $received = null;

        $dispatcher->subscribe(HookA::class, static function (HookA $h) use (&$received): void {
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

        $dispatcher->subscribe(HookA::class, static function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new HookB());

        Assert::false($called);
    }

    #[Test]
    public function unsubscribeViaReturnedClosure(): void
    {
        $dispatcher = new Dispatcher();
        $called = false;

        $unsubscribe = $dispatcher->subscribe(HookA::class, static function () use (&$called): void {
            $called = true;
        });

        $unsubscribe();
        $dispatcher->dispatch(new HookA());

        Assert::false($called);
    }

    #[Test]
    public function unsubscribeDuringDispatch(): void
    {
        $dispatcher = new Dispatcher();
        $callCount = 0;

        $dispatcher->subscribe(HookA::class, static function (HookA $hook, \Closure $unsubscribe) use (&$callCount): void {
            ++$callCount;
            $unsubscribe();
        });

        $dispatcher->dispatch(new HookA());
        $dispatcher->dispatch(new HookA());

        Assert::same($callCount, 1);
    }

    #[Test]
    public function handlerAddedDuringDispatchIsCalledInSameDispatch(): void
    {
        $dispatcher = new Dispatcher();
        $order = [];

        $dispatcher->subscribe(HookA::class, static function () use ($dispatcher, &$order): void {
            $order[] = 1;

            $dispatcher->subscribe(HookA::class, static function () use (&$order): void {
                $order[] = 2;
            });
        });

        $dispatcher->dispatch(new HookA());

        Assert::same($order, [1, 2]);
    }

    #[Test]
    public function remainingHandlersAreCalledAfterPeerUnsubscribes(): void
    {
        $dispatcher = new Dispatcher();
        $secondCalled = false;

        $dispatcher->subscribe(HookA::class, static function (HookA $hook, \Closure $unsubscribe): void {
            $unsubscribe();
        });
        $dispatcher->subscribe(HookA::class, static function () use (&$secondCalled): void {
            $secondCalled = true;
        });

        $dispatcher->dispatch(new HookA());

        Assert::true($secondCalled);
    }

    #[Test]
    public function worksWithEnum(): void
    {
        $dispatcher = new Dispatcher();
        $received = null;

        $dispatcher->subscribe(HookEnum::class, static function (HookEnum $hook) use (&$received): void {
            $received = $hook;
        });

        $dispatcher->dispatch(HookEnum::A);

        Assert::same($received, HookEnum::A);
    }

    /**
     * @param class-string $hookClass
     */
    #[Test]
    #[ExpectException(\ValueError::class)]
    #[DataSet([\stdClass::class], 'non-final class')]
    #[DataSet([\ReflectionType::class], 'abstract class')]
    #[DataSet([\Iterator::class], 'interface')]
    #[DataSet([HookTrait::class], 'trait')]
    public function throwsOnNonFinalHookClass(string $hookClass): void
    {
        new Dispatcher()->subscribe($hookClass, static function (): void {});
    }
}

final class HookA {}
final class HookB {}

/** @phpstan-ignore trait.unused */
trait HookTrait {}

enum HookEnum
{
    case A;
}
