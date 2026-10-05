<?php

declare(strict_types=1);

namespace Marko\Core\Event;

/**
 * Hands an async observer off to be run outside the current dispatch.
 *
 * Core defines this contract and ships no implementation. A queue package
 * (marko/queue) binds it; when nothing is bound, EventDispatcher throws as soon
 * as an observer marked #[Observer(async: true)] fires.
 */
interface AsyncObserverDispatcherInterface
{
    /**
     * Schedule the observer to handle the event later. The event must be serializable.
     */
    public function dispatch(
        string $observerClass,
        Event $event,
    ): void;
}
