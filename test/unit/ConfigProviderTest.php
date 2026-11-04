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

use Mezzio\Router\Exception\DuplicateRouteException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Webware\Router\ConfigProvider;
use Webware\Router\Http\QueryParamRoute;
use Webware\Router\QueryParamDuplicateRouteDetector;
use Webware\Router\QueryParamDuplicateRouteDetectorFactory;
use Webware\Router\QueryParamRouter;
use Webware\Router\QueryParamRouterFactory;

#[CoversClass(ConfigProvider::class)]
#[CoversClass(QueryParamRouterFactory::class)]
#[CoversClass(QueryParamDuplicateRouteDetectorFactory::class)]
#[UsesClass(QueryParamRoute::class)]
#[UsesClass(QueryParamRouter::class)]
#[UsesClass(QueryParamDuplicateRouteDetector::class)]
class ConfigProviderTest extends TestCase
{
    private ConfigProvider $provider;

    private MiddlewareInterface $middleware;

    #[Test]
    public function dependenciesIncludeDetectorFactory(): void
    {
        $dependencies = $this->provider->getDependencies();

        static::assertArrayHasKey(QueryParamDuplicateRouteDetector::class, $dependencies['factories']);
        static::assertSame(
            QueryParamDuplicateRouteDetectorFactory::class,
            $dependencies['factories'][QueryParamDuplicateRouteDetector::class],
        );
    }

    #[Test]
    public function dependenciesIncludeRouterFactory(): void
    {
        $dependencies = $this->provider->getDependencies();

        static::assertArrayHasKey(QueryParamRouter::class, $dependencies['factories']);
        static::assertSame(
            QueryParamRouterFactory::class,
            $dependencies['factories'][QueryParamRouter::class],
        );
    }

    #[Test]
    public function detectorFactoryCreatesDetector(): void
    {
        $container = $this->createStub(ContainerInterface::class);

        $factory  = new QueryParamDuplicateRouteDetectorFactory();
        $detector = $factory($container);

        static::assertInstanceOf(QueryParamDuplicateRouteDetector::class, $detector);
    }

    #[Test]
    public function getDependenciesReturnsDependencyConfiguration(): void
    {
        $dependencies = $this->provider->getDependencies();

        static::assertArrayHasKey('factories', $dependencies);
        static::assertArrayHasKey('aliases', $dependencies);
    }

    #[Test]
    public function getRouterConfigReturnsDefaultConfiguration(): void
    {
        $config = $this->provider->getRouterConfig();

        static::assertArrayHasKey('detect_duplicates', $config);
        static::assertTrue($config['detect_duplicates']);
    }

    #[Test]
    public function invokeReturnsArray(): void
    {
        $config = ($this->provider)();

        static::assertArrayHasKey(QueryParamRouter::class, $config);
        static::assertArrayHasKey('dependencies', $config);
        static::assertSame($this->provider->getDependencies(), $config['dependencies']);
    }

    #[Test]
    public function routerFactoryCreatesRouterWithDuplicateDetectionDisabled(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container
            ->method('get')
            ->willReturn([
                QueryParamRouter::class => ['detect_duplicates' => false],
            ]);

        $factory = new QueryParamRouterFactory();
        $router  = $factory($container);

        $router->addRoute(new QueryParamRoute('/api', $this->middleware, ['action']));
        $router->addRoute(new QueryParamRoute('/api', $this->middleware, ['action']));

        static::assertInstanceOf(QueryParamRouter::class, $router);
    }

    #[Test]
    public function routerFactoryCreatesRouterWithDuplicateDetectionEnabled(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('has')
            ->willReturnMap([
                ['config',                                true],
                [QueryParamDuplicateRouteDetector::class, false],
            ]);
        $container
            ->method('get')
            ->willReturn([
                QueryParamRouter::class => ['detect_duplicates' => true],
            ]);

        $factory = new QueryParamRouterFactory();
        $router  = $factory($container);

        $router->addRoute(new QueryParamRoute('/api', $this->middleware, ['action']));

        $this->expectException(DuplicateRouteException::class);
        $router->addRoute(new QueryParamRoute('/api', $this->middleware, ['action']));
    }

    #[Test]
    public function routerFactoryUsesDefaultsWhenNoConfigPresent(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->expects(static::never())->method('get');

        $factory = new QueryParamRouterFactory();
        $router  = $factory($container);

        $router->addRoute(new QueryParamRoute('/api', $this->middleware, ['action']));

        $this->expectException(DuplicateRouteException::class);
        $router->addRoute(new QueryParamRoute('/api', $this->middleware, ['action']));
    }

    #[Test]
    public function routerFactoryUsesDuplicateDetectorFromContainer(): void
    {
        $detector = new QueryParamDuplicateRouteDetector();
        $detector->detectDuplicate(new QueryParamRoute('/api', $this->middleware, ['action']));

        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('has')
            ->willReturnMap([
                ['config',                                false],
                [QueryParamDuplicateRouteDetector::class, true],
            ]);
        $container->method('get')->willReturn($detector);

        $factory = new QueryParamRouterFactory();
        $router  = $factory($container);

        $this->expectException(DuplicateRouteException::class);
        $router->addRoute(new QueryParamRoute('/api', $this->middleware, ['action']));
    }

    protected function setUp(): void
    {
        $this->provider   = new ConfigProvider();
        $this->middleware = $this->createStub(MiddlewareInterface::class);
    }
}
