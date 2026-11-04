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
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Webware\Router\QueryParamDuplicateRouteDetector;
use Webware\Router\QueryParamRoute;

#[CoversClass(QueryParamDuplicateRouteDetector::class)]
#[UsesClass(QueryParamRoute::class)]
class QueryParamDuplicateRouteDetectorTest extends TestCase
{
    private QueryParamDuplicateRouteDetector $detector;

    private MiddlewareInterface $middleware;

    #[DoesNotPerformAssertions]
    #[Test]
    public function allowsEmptyQueryParamsOnDifferentPaths(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, []);
        $route2 = new QueryParamRoute('/other', $this->middleware, []);

        $this->detector->detectDuplicate($route1);
        $this->detector->detectDuplicate($route2); // Should not throw
    }

    #[DoesNotPerformAssertions]
    #[Test]
    public function allowsSamePathDifferentQueryParamCount(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action', 'type']);

        $this->detector->detectDuplicate($route1);
        $this->detector->detectDuplicate($route2); // Should not throw
    }

    #[DoesNotPerformAssertions]
    #[Test]
    public function allowsSamePathQueryParamsWithDifferentMethods(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action'], ['GET']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action'], ['POST']);

        $this->detector->detectDuplicate($route1);
        $this->detector->detectDuplicate($route2); // Should not throw
    }

    #[DoesNotPerformAssertions]
    #[Test]
    public function allowsSamePathWithDifferentQueryParams(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['type']);

        $this->detector->detectDuplicate($route1);
        $this->detector->detectDuplicate($route2); // Should not throw
    }

    // Value constraint tests

    #[DoesNotPerformAssertions]
    #[Test]
    public function allowsSamePathWithDifferentValueConstraints(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action' => 'profile', 'u' => null]);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action' => 'edit', 'u' => null]);

        $this->detector->detectDuplicate($route1);
        $this->detector->detectDuplicate($route2); // Should not throw - different value constraints
    }

    #[DoesNotPerformAssertions]
    #[Test]
    public function allowsWildcardAndSpecificValueForSameKey(): void
    {
        // Wildcard route (any value)
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action' => null, 'u' => null]);

        // Specific value route
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action' => 'profile', 'u' => null]);

        $this->detector->detectDuplicate($route1);
        $this->detector->detectDuplicate($route2); // Should not throw - different constraints
    }

    #[Test]
    public function duplicateMessageDescribesValueConstraints(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action' => 'profile', 'u' => null]);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action' => 'profile', 'u' => null]);

        $this->detector->detectDuplicate($route1);

        try {
            $this->detector->detectDuplicate($route2);
            static::fail('Expected a DuplicateRouteException to be thrown');
        } catch (DuplicateRouteException $exception) {
            static::assertStringContainsString(
                'with query params [action=profile,u]',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function duplicateMessageListsSpecificMethods(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action'], ['GET']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action'], ['GET']);

        $this->detector->detectDuplicate($route1);

        try {
            $this->detector->detectDuplicate($route2);
            static::fail('Expected a DuplicateRouteException to be thrown');
        } catch (DuplicateRouteException $exception) {
            static::assertStringContainsString('answering to methods [GET]', $exception->getMessage());
        }
    }

    #[Test]
    public function duplicateMessageOmitsQueryParamsWhenNoneAreRequired(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, [], null, 'api-route');
        $route2 = new QueryParamRoute('/other', $this->middleware, [], null, 'api-route');

        $this->detector->detectDuplicate($route1);

        try {
            $this->detector->detectDuplicate($route2);
            static::fail('Expected a DuplicateRouteException to be thrown');
        } catch (DuplicateRouteException $exception) {
            static::assertStringNotContainsString('query params', $exception->getMessage());
        }
    }

    #[Test]
    public function duplicateMessageReportsAnyForRoutesWithoutMethods(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action'], null, 'api-route');
        $route2 = new QueryParamRoute('/other', $this->middleware, ['action'], null, 'api-route');

        $this->detector->detectDuplicate($route1);

        try {
            $this->detector->detectDuplicate($route2);
            static::fail('Expected a DuplicateRouteException to be thrown');
        } catch (DuplicateRouteException $exception) {
            static::assertStringContainsString('answering to methods [(any)]', $exception->getMessage());
        }
    }

    #[DoesNotPerformAssertions]
    #[Test]
    public function mixedConstraintsNotConsideredDuplicate(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action' => 'profile', 'type' => null]);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action' => null, 'type' => 'user']);

        $this->detector->detectDuplicate($route1);
        $this->detector->detectDuplicate($route2); // Should not throw - different constraints
    }

    #[DoesNotPerformAssertions]
    #[Test]
    public function queryParamKeysAreCaseSensitive(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['Action']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action']);

        $this->detector->detectDuplicate($route1);
        $this->detector->detectDuplicate($route2); // Should not throw (different case)
    }

    #[Test]
    public function queryParamKeysAreOrderIndependent(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action', 'type']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['type', 'action']);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->detector->detectDuplicate($route2);
    }

    #[Test]
    public function throwsOnDuplicateEmptyQueryParamsSamePath(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, []);
        $route2 = new QueryParamRoute('/api', $this->middleware, []);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->detector->detectDuplicate($route2);
    }

    #[Test]
    public function throwsOnDuplicatePathAndQueryParams(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action']);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->expectExceptionMessage('Duplicate route detected');
        $this->detector->detectDuplicate($route2);
    }

    #[Test]
    public function throwsOnDuplicatePathQueryParamsAndMethod(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action'], ['GET']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action'], ['GET']);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->detector->detectDuplicate($route2);
    }

    #[Test]
    public function throwsOnDuplicateRouteName(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action'], null, 'api-route');
        $route2 = new QueryParamRoute('/other', $this->middleware, ['type'], null, 'api-route');

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->expectExceptionMessage('name "api-route"');
        $this->detector->detectDuplicate($route2);
    }

    #[Test]
    public function throwsOnMethodOverlapWhenEarlierMethodIsNotRegistered(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action'], ['POST']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action'], ['GET', 'POST']);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->detector->detectDuplicate($route2);
    }

    #[Test]
    public function throwsOnMethodOverlapWithMultipleMethods(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action'], ['GET', 'POST']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action'], ['POST', 'PUT']);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->expectExceptionMessage('POST');
        $this->detector->detectDuplicate($route2);
    }

    #[Test]
    public function throwsOnSamePathAndValueConstraints(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action' => 'profile', 'u' => null]);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action' => 'profile', 'u' => null]);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->detector->detectDuplicate($route2);
    }

    #[Test]
    public function throwsWhenAnyMethodRouteExistsAndSpecificMethodAdded(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action']); // any method
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action'], ['GET']);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->detector->detectDuplicate($route2);
    }

    #[Test]
    public function throwsWhenSpecificMethodExistsAndAnyMethodAdded(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action'], ['GET']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action']); // any method

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->detector->detectDuplicate($route2);
    }

    #[Test]
    public function valueConstraintsAreOrderIndependent(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action' => 'profile', 'type' => 'user']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['type' => 'user', 'action' => 'profile']);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->detector->detectDuplicate($route2); // Should throw - same constraints, different order
    }

    protected function setUp(): void
    {
        $this->detector   = new QueryParamDuplicateRouteDetector();
        $this->middleware = $this->createStub(MiddlewareInterface::class);
    }
}
