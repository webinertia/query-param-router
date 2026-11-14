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

namespace WebwareTest\Router;

use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Router\RouteProviderInterface;
use WebwareTestAsset\Router\RecordingRouteProvider;

/**
 * The interface declares no executable code, so it cannot be a coverage
 * target. The test proves the contract is implementable and usable with a
 * real RouteCollector.
 */
#[CoversNothing]
final class RouteProviderInterfaceTest extends TestCase
{
    #[Test]
    public function registerRoutesReceivesTheCollectorItWasPassed(): void
    {
        $router    = $this->createStub(RouterInterface::class);
        $collector = new RouteCollector($router);
        $provider  = new RecordingRouteProvider();

        static::assertInstanceOf(RouteProviderInterface::class, $provider);
        static::assertInstanceOf(RouteCollectorInterface::class, $collector);

        $provider->registerRoutes($collector);

        static::assertSame([$collector], $provider->getCollectors());
        static::assertSame($collector, $provider->getCollectors()[0]);
    }
}
