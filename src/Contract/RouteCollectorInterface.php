<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Content Bundle - Content publishing with Symfony.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\ContentBundle\Contract;

use Derafu\Routing\Contract\RouteInterface;
use Derafu\Routing\Contract\RouteRegistrarInterface;
use Derafu\Translation\Exception\Core\TranslatableLogicException;

/**
 * Collects plugin route registrations while Symfony compiles its route catalogue.
 *
 * The host supplies the same instance for this capability and
 * {@see RouteRegistrarInterface}.
 * Registrations outside a collection do not publish runtime routes.
 */
interface RouteCollectorInterface
{
    /**
     * Runs plugin initialization and returns the routes registered during it.
     *
     * Collection state is cleared when initialization finishes or throws.
     *
     * @param callable(): mixed $initialize Synchronous plugin initialization.
     * @return array<RouteInterface> Routes registered by the callback, in order.
     * @throws TranslatableLogicException If a collection is already in progress.
     */
    public function collect(callable $initialize): array;
}
