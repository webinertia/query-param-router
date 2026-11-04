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

use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\Exception\DuplicateRouteException;
use Mezzio\Router\Exception\RuntimeException;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Webware\Router\Http\QueryParamRoute;
use Webware\Router\QueryParamDuplicateRouteDetector;
use Webware\Router\QueryParamRouter;

#[CoversClass(QueryParamRouter::class)]
#[UsesClass(QueryParamRoute::class)]
#[UsesClass(QueryParamDuplicateRouteDetector::class)]
class QueryParamRouterTest extends TestCase
{
    private QueryParamRouter $router;

    private MiddlewareInterface $middleware;

    #[DoesNotPerformAssertions]
    #[Test]
    public function addRouteStoresRoute(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, ['action']);
        $this->router->addRoute($route);
    }

    #[Test]
    public function doesNotMatchRouteWithWrongValue(): void
    {
        $route = new QueryParamRoute('/', $this->middleware, ['action' => 'profile', 'u' => null]);
        $this->router->addRoute($route);

        // Should NOT match when action=edit
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'edit', 'u' => '1']);

        $result = $this->router->match($request);
        static::assertTrue($result->isFailure());
    }

    #[Test]
    public function generateUriCombinesSubstitutionsAndQueryParams(): void
    {
        $route = new QueryParamRoute('/users/{id}', $this->middleware, ['action'], null, 'user-route');
        $this->router->addRoute($route);

        $uri = $this->router->generateUri('user-route', ['id' => '123'], ['query' => ['action' => 'edit']]);

        static::assertStringContainsString('/users/123', $uri);
        static::assertStringContainsString('action=edit', $uri);
    }

    #[Test]
    public function generateUriHandlesSubstitutions(): void
    {
        $route = new QueryParamRoute('/users/{id}', $this->middleware, ['action'], null, 'user-route');
        $this->router->addRoute($route);

        $uri = $this->router->generateUri('user-route', ['id' => '123']);

        static::assertStringContainsString('/users/123', $uri);
    }

    #[Test]
    public function generateUriIncludesQueryParamsFromOptions(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, ['action'], null, 'api-route');
        $this->router->addRoute($route);

        $uri = $this->router->generateUri('api-route', [], ['query' => ['action' => 'create', 'type' => 'user']]);

        static::assertStringContainsString('/api?', $uri);
        static::assertStringContainsString('action=create', $uri);
        static::assertStringContainsString('type=user', $uri);
    }

    #[Test]
    public function generateUriReturnsPathForBasicRoute(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, ['action'], null, 'api-route');
        $this->router->addRoute($route);

        $uri = $this->router->generateUri('api-route');

        static::assertSame('/api', $uri);
    }

    #[Test]
    public function generateUriThrowsForUnknownRoute(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot generate URI');

        $this->router->generateUri('unknown-route');
    }

    #[Test]
    public function implementsRouterInterface(): void
    {
        static::assertInstanceOf(RouterInterface::class, $this->router);
    }

    #[Test]
    public function matchAllowsAnyMethodWhenNotSpecified(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, ['action']);
        $this->router->addRoute($route);

        $getRequest = new ServerRequest([], [], '/api', 'GET');
        $getRequest = $getRequest->withQueryParams(['action' => 'create']);

        $postRequest = new ServerRequest([], [], '/api', 'POST');
        $postRequest = $postRequest->withQueryParams(['action' => 'create']);

        static::assertTrue($this->router->match($getRequest)->isSuccess());
        static::assertTrue($this->router->match($postRequest)->isSuccess());
    }

    // Value constraint routing tests

    #[Test]
    public function matchesRouteWithSpecificValueConstraint(): void
    {
        $route = new QueryParamRoute('/', $this->middleware, ['action' => 'profile', 'u' => null]);
        $this->router->addRoute($route);

        // Should match when action=profile
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'profile', 'u' => '1']);

        $result = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame($route->toRoute(), $result->getMatchedRoute());
    }

    #[Test]
    public function matchExcludesNonRequiredQueryParams(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, ['action']);
        $this->router->addRoute($route);

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['action' => 'create', 'extra' => 'value']);

        $result = $this->router->match($request);

        static::assertTrue($result->isSuccess());
        $matchedParams = $result->getMatchedParams();
        static::assertSame('create', $matchedParams['action']);
        static::assertArrayNotHasKey('extra', $matchedParams);
    }

    #[Test]
    public function matchIncludesQueryParamValuesInMatchedParams(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, ['action', 'type']);
        $this->router->addRoute($route);

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['action' => 'create', 'type' => 'user']);

        $result = $this->router->match($request);

        static::assertTrue($result->isSuccess());
        $matchedParams = $result->getMatchedParams();
        static::assertSame('create', $matchedParams['action']);
        static::assertSame('user', $matchedParams['type']);
    }

    #[Test]
    public function matchPrefersFirstRegisteredRouteOnEqualScores(): void
    {
        $first  = new QueryParamRoute('/api', $this->middleware, ['action'], null, 'first');
        $second = new QueryParamRoute('/api', $this->middleware, ['type'], null, 'second');

        $this->router->addRoute($first);
        $this->router->addRoute($second);

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['action' => 'create', 'type' => 'user']);

        static::assertSame('first', $this->router->match($request)->getMatchedRouteName());
    }

    #[Test]
    public function matchPrefersRouteWithMoreValueConstraints(): void
    {
        $single = new QueryParamRoute('/api', $this->middleware, ['action' => 'create', 'type'], null, 'single');
        $double = new QueryParamRoute(
            '/api',
            $this->middleware,
            ['action' => 'create', 'type' => 'user'],
            null,
            'double',
        );

        $this->router->addRoute($single);
        $this->router->addRoute($double);

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['action' => 'create', 'type' => 'user']);

        static::assertSame('double', $this->router->match($request)->getMatchedRouteName());
    }

    #[Test]
    public function matchPrefersRouteWithValueConstraintOverMoreKeys(): void
    {
        $plain  = new QueryParamRoute('/api', $this->middleware, ['action', 'type'], null, 'plain');
        $valued = new QueryParamRoute('/api', $this->middleware, ['action' => null, 'type' => 'user'], null, 'valued');

        $this->router->addRoute($plain);
        $this->router->addRoute($valued);

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['action' => 'create', 'type' => 'user']);

        static::assertSame('valued', $this->router->match($request)->getMatchedRouteName());
    }

    #[Test]
    public function matchQueryParamKeysAreCaseSensitive(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, ['Action']);
        $this->router->addRoute($route);

        $request1 = new ServerRequest([], [], '/api', 'GET');
        $request1 = $request1->withQueryParams(['Action' => 'create']);

        $request2 = new ServerRequest([], [], '/api', 'GET');
        $request2 = $request2->withQueryParams(['action' => 'create']);

        static::assertTrue($this->router->match($request1)->isSuccess());
        static::assertTrue($this->router->match($request2)->isFailure());
    }

    #[Test]
    public function matchRespectsHttpMethodWhenSpecified(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, ['action'], ['POST']);
        $this->router->addRoute($route);

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['action' => 'create']);

        $result = $this->router->match($request);

        static::assertTrue($result->isFailure());
    }

    #[Test]
    public function matchReturnsFailureForMissingQueryParam(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, ['action']);
        $this->router->addRoute($route);

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['other' => 'value']);

        $result = $this->router->match($request);

        static::assertTrue($result->isFailure());
        static::assertFalse($result->isSuccess());
    }

    #[Test]
    public function matchReturnsFailureForNonMatchingPath(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, ['action']);
        $this->router->addRoute($route);

        $request = new ServerRequest([], [], '/other', 'GET');
        $request = $request->withQueryParams(['action' => 'create']);

        $result = $this->router->match($request);

        static::assertTrue($result->isFailure());
    }

    #[Test]
    public function matchReturnsSuccessForMatchingPathAndQueryParams(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, ['action']);
        $this->router->addRoute($route);

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['action' => 'create']);

        $result = $this->router->match($request);

        static::assertTrue($result->isSuccess());
        static::assertSame($route->toRoute(), $result->getMatchedRoute());
    }

    #[Test]
    public function matchSelectsMostSpecificRoute(): void
    {
        $route1 = new QueryParamRoute('/api', $this->middleware, ['action']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action', 'type']);

        $this->router->addRoute($route1);
        $this->router->addRoute($route2);

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['action' => 'create', 'type' => 'user']);

        $result = $this->router->match($request);

        static::assertTrue($result->isSuccess());
        static::assertSame($route2->toRoute(), $result->getMatchedRoute());
    }

    #[Test]
    public function matchWithoutQueryParamsMatchesEmptyQueryRoute(): void
    {
        $route = new QueryParamRoute('/api', $this->middleware, []);
        $this->router->addRoute($route);

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams([]);

        $result = $this->router->match($request);

        static::assertTrue($result->isSuccess());
    }

    #[Test]
    public function multipleRoutesWithDifferentValueConstraints(): void
    {
        $profileRoute = new QueryParamRoute('/', $this->middleware, ['action' => 'profile'], null, 'profile');
        $editRoute    = new QueryParamRoute('/', $this->middleware, ['action' => 'edit'], null, 'edit');
        $deleteRoute  = new QueryParamRoute('/', $this->middleware, ['action' => 'delete'], null, 'delete');

        $this->router->addRoute($profileRoute);
        $this->router->addRoute($editRoute);
        $this->router->addRoute($deleteRoute);

        // Test profile
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'profile']);
        static::assertSame('profile', $this->router->match($request)->getMatchedRouteName());

        // Test edit
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'edit']);
        static::assertSame('edit', $this->router->match($request)->getMatchedRouteName());

        // Test delete
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'delete']);
        static::assertSame('delete', $this->router->match($request)->getMatchedRouteName());
    }

    #[Test]
    public function routerWithDuplicateDetector(): void
    {
        $detector = new QueryParamDuplicateRouteDetector();
        $router   = new QueryParamRouter($detector);

        $route1 = new QueryParamRoute('/api', $this->middleware, ['action']);
        $router->addRoute($route1);

        // This should throw due to duplicate detection
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action']);

        $this->expectException(DuplicateRouteException::class);
        $router->addRoute($route2);
    }

    #[DoesNotPerformAssertions]
    #[Test]
    public function routerWithoutDuplicateDetectorAllowsDuplicates(): void
    {
        $router = new QueryParamRouter(null);

        $route1 = new QueryParamRoute('/api', $this->middleware, ['action']);
        $route2 = new QueryParamRoute('/api', $this->middleware, ['action']);

        $router->addRoute($route1);
        $router->addRoute($route2); // Should not throw
    }

    #[Test]
    public function selectsMostSpecificRouteByValue(): void
    {
        // Route with specific value constraint
        $profileRoute = new QueryParamRoute(
            '/',
            $this->middleware,
            ['action' => 'profile', 'u' => null],
            null,
            'profile',
        );

        // Route with wildcard (any value)
        $wildcardRoute = new QueryParamRoute('/', $this->middleware, ['action' => null, 'u' => null], null, 'wildcard');

        $this->router->addRoute($wildcardRoute);
        $this->router->addRoute($profileRoute);

        // Should match the more specific route (with value constraint)
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'profile', 'u' => '1']);

        $result = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame('profile', $result->getMatchedRouteName());
    }

    #[Test]
    public function selectsWildcardRouteWhenValueDoesNotMatch(): void
    {
        // Route with specific value constraint
        $profileRoute = new QueryParamRoute(
            '/',
            $this->middleware,
            ['action' => 'profile', 'u' => null],
            null,
            'profile',
        );

        // Route with wildcard (any value)
        $wildcardRoute = new QueryParamRoute('/', $this->middleware, ['action' => null, 'u' => null], null, 'wildcard');

        $this->router->addRoute($wildcardRoute);
        $this->router->addRoute($profileRoute);

        // Should match the wildcard route when action=edit
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'edit', 'u' => '1']);

        $result = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame('wildcard', $result->getMatchedRouteName());
    }

    #[Test]
    public function valueConstraintsWithMultipleParameters(): void
    {
        $route = new QueryParamRoute(
            '/',
            $this->middleware,
            ['action' => 'profile', 'section' => 'settings', 'u' => null],
        );
        $this->router->addRoute($route);

        // Should match when both constrained values match
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'profile', 'section' => 'settings', 'u' => '1']);
        static::assertTrue($this->router->match($request)->isSuccess());

        // Should NOT match when action is wrong
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'edit', 'section' => 'settings', 'u' => '1']);
        static::assertTrue($this->router->match($request)->isFailure());

        // Should NOT match when section is wrong
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'profile', 'section' => 'other', 'u' => '1']);
        static::assertTrue($this->router->match($request)->isFailure());
    }

    protected function setUp(): void
    {
        $this->router     = new QueryParamRouter();
        $this->middleware = $this->createStub(MiddlewareInterface::class);
    }
}
