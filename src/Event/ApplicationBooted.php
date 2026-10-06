<?php

declare(strict_types=1);

namespace Marko\Core\Event;

/**
 * Dispatched once per boot, after every module's `boot` callback has run, on
 * live and cached boots alike.
 *
 * Observe it to check state that other modules set up in their boot
 * callbacks (for example guard drivers registered through
 * GuardDriverRegistry::extend()), which a module's own boot callback cannot
 * see when those modules boot after it. Observers run on every boot, so keep
 * them cheap on the request path: CachedDiscovery::isCached() tells a cached
 * boot apart.
 */
class ApplicationBooted extends Event {}
