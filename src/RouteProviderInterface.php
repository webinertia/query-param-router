<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Query Param Router package.
 *
 * Copyright (c) 2026 Joey (aka Tyrsson) Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\Router;

use Mezzio\Router\RouteCollectorInterface;

/**
 * Provides routes to a route collector.
 *
 * Replacement for Mezzio\Router\RouteProviderInterface that does not require
 * mezzio/mezzio. The middleware factory parameter is absent because
 * Mezzio\MiddlewareFactoryInterface is also supplied only by mezzio/mezzio;
 * consumers that register no prepared middleware pass a no-op middleware to
 * the collector instead.
 *
 * @api
 */
interface RouteProviderInterface
{
    public function registerRoutes(RouteCollectorInterface $routeCollector): void;
}
