<?php

declare(strict_types=1);

namespace Thesis;

/**
 * @api
 */
final class Dispatcher
{
    /**
     * @var array<class-string, array<non-negative-int, \Closure(object): void>>
     */
    private array $handlers = [];

    /**
     * @template T of object
     * @param class-string<T> $hookClass
     * @param callable(T, \Closure(): void): void $handler second argument is an unsubscribe callback
     * @return \Closure(): void unsubscribe callback
     */
    public function subscribe(string $hookClass, callable $handler): \Closure
    {
        $index = (array_key_last($this->handlers[$hookClass] ?? []) ?? -1) + 1;

        $unsubscribe = $this->createUnsubscribeCallback($hookClass, $index);

        /** @phpstan-ignore argument.type */
        $this->handlers[$hookClass][$index] = static fn(object $hook) => $handler($hook, $unsubscribe);

        return $unsubscribe;
    }

    public function dispatch(object $hook): void
    {
        if (!isset($this->handlers[$hook::class])) {
            return;
        }

        $dispatched = [];

        /** @phpstan-ignore nullCoalesce.offset */
        while ([] !== $handlers = array_diff_key($this->handlers[$hook::class] ?? [], $dispatched)) {
            foreach ($handlers as $index => $handler) {
                $handler($hook);
                $dispatched[$index] = true;
            }
        }
    }

    /**
     * @param class-string $hookClass
     * @param non-negative-int $index
     * @return \Closure(): void
     */
    private function createUnsubscribeCallback(string $hookClass, int $index): \Closure
    {
        $handlers = &$this->handlers;

        return static function () use (&$handlers, $hookClass, $index): void {
            if (!isset($handlers[$hookClass][$index])) {
                return;
            }

            unset($handlers[$hookClass][$index]);

            /** @phpstan-ignore offsetAccess.notFound */
            if ($handlers[$hookClass] === []) {
                unset($handlers[$hookClass]);
            }
        };
    }
}
