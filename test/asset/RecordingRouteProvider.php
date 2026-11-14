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

namespace WebwareTestAsset\Router;

use Mezzio\Router\RouteCollectorInterface;
use Override;
use Webware\Router\RouteProviderInterface;

/**
 * Route provider that records every collector handed to registerRoutes().
 */
final class RecordingRouteProvider implements RouteProviderInterface
{
    /**
     * @var list<RouteCollectorInterface>
     */
    private array $collectors = [];

    /**
     * @return list<RouteCollectorInterface>
     */
    public function getCollectors(): array
    {
        return $this->collectors;
    }

    #[Override]
    public function registerRoutes(RouteCollectorInterface $routeCollector): void
    {
        $this->collectors[] = $routeCollector;
    }
}
