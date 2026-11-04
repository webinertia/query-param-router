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

namespace WebwareTestIntegration\Router;

use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Router\QueryParamDuplicateRouteDetector;
use Webware\Router\QueryParamRoute;
use Webware\Router\QueryParamRouter;

#[CoversClass(QueryParamRouter::class)]
#[UsesClass(QueryParamRoute::class)]
#[UsesClass(QueryParamDuplicateRouteDetector::class)]
class QueryParamRouterIntegrationTest extends TestCase
{
    private QueryParamRouter $router;

    #[Test]
    public function completeRoutingWorkflowWithMiddlewarePipe(): void
    {
        // Set up routes
        $createHandler = $this->createHandler('create-response');
        $listHandler   = $this->createHandler('list-response');

        $createRoute = new QueryParamRoute('/api', $createHandler, ['action'], ['POST'], 'api-create');
        $listRoute   = new QueryParamRoute('/api', $listHandler, ['action'], ['GET'], 'api-list');

        $this->router->addRoute($createRoute);
        $this->router->addRoute($listRoute);

        // Create middleware pipeline
        $pipe = new MiddlewarePipe();

        // Add routing middleware
        $pipe->pipe($this->createRoutingMiddleware());

        // Add dispatch middleware
        $pipe->pipe($this->createDispatchMiddleware());

        // Test POST request with action=create
        $request = new ServerRequest([], [], '/api', 'POST');
        $request = $request->withQueryParams(['action' => 'create']);

        $response = $pipe->process($request, $this->createFinalHandler());

        static::assertSame('create-response', (string) $response->getBody());

        // Test GET request with action=list
        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['action' => 'list']);

        $response = $pipe->process($request, $this->createFinalHandler());

        static::assertSame('list-response', (string) $response->getBody());
    }

    #[Test]
    public function emptyQueryParamsRouteMatchesAnyQueryParams(): void
    {
        $middleware = $this->createHandler('matched');
        $route      = new QueryParamRoute('/api', $middleware, []);

        $this->router->addRoute($route);

        // Should match with no query params
        $request1 = new ServerRequest([], [], '/api', 'GET');
        $result1  = $this->router->match($request1);
        static::assertTrue($result1->isSuccess());

        // Should also match with any query params
        $request2 = new ServerRequest([], [], '/api', 'GET');
        $request2 = $request2->withQueryParams(['anything' => 'goes']);
        $result2  = $this->router->match($request2);
        static::assertTrue($result2->isSuccess());
    }

    #[Test]
    public function failureScenarioReturns404(): void
    {
        $middleware = $this->createHandler('success');
        $route      = new QueryParamRoute('/api', $middleware, ['action']);

        $this->router->addRoute($route);

        // Request missing required query param
        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['other' => 'value']);

        $result = $this->router->match($request);

        static::assertTrue($result->isFailure());
        static::assertFalse($result->isSuccess());
    }

    #[Test]
    public function multipleQueryParamsAreMatched(): void
    {
        $middleware = $this->createHandler('matched');
        $route      = new QueryParamRoute('/api', $middleware, ['action', 'type', 'id']);

        $this->router->addRoute($route);

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams([
            'action' => 'update',
            'type'   => 'user',
            'id'     => '123',
            'extra'  => 'ignored',
        ]);

        $result = $this->router->match($request);

        static::assertTrue($result->isSuccess());
        $params = $result->getMatchedParams();
        static::assertSame('update', $params['action']);
        static::assertSame('user', $params['type']);
        static::assertSame('123', $params['id']);
        static::assertArrayNotHasKey('extra', $params);
    }

    #[Test]
    public function realWorldSMFStyleRouting(): void
    {
        // SMF-style routes with action and user parameters
        $profileHandler = $this->createHandler('user-profile');
        $pmHandler      = $this->createHandler('private-messages');
        $adminHandler   = $this->createHandler('admin-panel');

        $profileRoute = new QueryParamRoute('/', $profileHandler, ['action' => 'profile', 'u' => null]);
        $pmRoute      = new QueryParamRoute('/', $pmHandler, ['action' => 'pm']);
        $adminRoute   = new QueryParamRoute('/', $adminHandler, ['action' => 'admin', 'area' => null]);

        $this->router->addRoute($profileRoute);
        $this->router->addRoute($pmRoute);
        $this->router->addRoute($adminRoute);

        // Test profile with user ID
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'profile', 'u' => '123']);
        $result  = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame($profileRoute->toRoute(), $result->getMatchedRoute());
        $params = $result->getMatchedParams();
        static::assertSame('profile', $params['action']);
        static::assertSame('123', $params['u']);

        // Test PM without extra params
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'pm']);
        $result  = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame($pmRoute->toRoute(), $result->getMatchedRoute());

        // Test admin with area
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'admin', 'area' => 'settings']);
        $result  = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame($adminRoute->toRoute(), $result->getMatchedRoute());
    }

    #[Test]
    public function routeMatchesWithKeyPresentRegardlessOfValue(): void
    {
        $middleware = $this->createHandler('matched');
        $route      = new QueryParamRoute('/', $middleware, ['board']);

        $this->router->addRoute($route);

        // Route should match when 'board' key is present with any value
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['board' => '100.2']);

        $result = $this->router->match($request);

        static::assertTrue($result->isSuccess());
        $params = $result->getMatchedParams();
        static::assertSame('100.2', $params['board']);
    }

    #[Test]
    public function routePriorityWithMultipleMatchingRoutes(): void
    {
        $handler1 = $this->createHandler('handler1');
        $handler2 = $this->createHandler('handler2');
        $handler3 = $this->createHandler('handler3');

        // Add routes with different specificity
        $route1 = new QueryParamRoute('/api', $handler1, ['action']);
        $route2 = new QueryParamRoute('/api', $handler2, ['action', 'type']);
        $route3 = new QueryParamRoute('/api', $handler3, ['action', 'type', 'id']);

        $this->router->addRoute($route1);
        $this->router->addRoute($route2);
        $this->router->addRoute($route3);

        // Request with all three params should match the most specific route
        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams([
            'action' => 'update',
            'type'   => 'user',
            'id'     => '123',
        ]);

        $result = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame($route3->toRoute(), $result->getMatchedRoute());

        // Request with two params should match the second route
        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams([
            'action' => 'list',
            'type'   => 'user',
        ]);

        $result = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame($route2->toRoute(), $result->getMatchedRoute());

        // Request with one param should match the first route
        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['action' => 'list']);

        $result = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame($route1->toRoute(), $result->getMatchedRoute());
    }

    #[Test]
    public function routeResultAttributeIsSetInRequest(): void
    {
        $middleware = new class() implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $routeResult = $request->getAttribute(RouteResult::class);

                if ($routeResult instanceof RouteResult && $routeResult->isSuccess()) {
                    /** @var array<string, mixed> $params */
                    $params = $routeResult->getMatchedParams();

                    /** @var string $action */
                    $action = $params['action'] ?? 'none';
                    $txt    = "action:{$action}";

                    return new TextResponse($txt);
                }

                return new TextResponse('no-route');
            }
        };

        $route = new QueryParamRoute('/api', $middleware, ['action']);
        $this->router->addRoute($route);

        // Create pipeline
        $pipe = new MiddlewarePipe();
        $pipe->pipe($this->createRoutingMiddleware());
        $pipe->pipe($this->createDispatchMiddleware());

        $request = new ServerRequest([], [], '/api', 'GET');
        $request = $request->withQueryParams(['action' => 'create']);

        $response = $pipe->process($request, $this->createFinalHandler());

        static::assertSame('action:create', (string) $response->getBody());
    }

    #[Test]
    public function uriGenerationInRealWorldScenario(): void
    {
        $middleware = $this->createHandler('response');
        $route      = new QueryParamRoute('/users/{id}/posts', $middleware, ['action'], null, 'user-posts');

        $this->router->addRoute($route);

        // Generate URI with path substitution and query params
        $uri = $this->router->generateUri(
            'user-posts',
            ['id' => '42'],
            ['query' => ['action' => 'edit', 'draft' => 'true']],
        );

        static::assertStringContainsString('/users/42/posts', $uri);
        static::assertStringContainsString('action=edit', $uri);
        static::assertStringContainsString('draft=true', $uri);
    }

    // Value constraint integration tests

    #[Test]
    public function valueConstraintRouting(): void
    {
        $profileHandler  = $this->createHandler('profile-page');
        $editHandler     = $this->createHandler('edit-page');
        $wildcardHandler = $this->createHandler('wildcard-page');

        // Routes with specific value constraints
        $profileRoute = new QueryParamRoute('/', $profileHandler, ['action' => 'profile', 'u' => null]);
        $editRoute    = new QueryParamRoute('/', $editHandler, ['action' => 'edit', 'u' => null]);

        // Wildcard route as fallback
        $wildcardRoute = new QueryParamRoute('/', $wildcardHandler, ['action' => null, 'u' => null]);

        $this->router->addRoute($wildcardRoute);
        $this->router->addRoute($profileRoute);
        $this->router->addRoute($editRoute);

        // Test profile route
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'profile', 'u' => '1']);
        $result  = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame($profileRoute->toRoute(), $result->getMatchedRoute());

        // Test edit route
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'edit', 'u' => '1']);
        $result  = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame($editRoute->toRoute(), $result->getMatchedRoute());

        // Test wildcard fallback
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['action' => 'other', 'u' => '1']);
        $result  = $this->router->match($request);
        static::assertTrue($result->isSuccess());
        static::assertSame($wildcardRoute->toRoute(), $result->getMatchedRoute());
    }

    #[Test]
    public function valueConstraintWithMiddlewarePipeline(): void
    {
        $profileHandler = $this->createHandler('profile-response');
        $editHandler    = $this->createHandler('edit-response');

        $profileRoute = new QueryParamRoute('/', $profileHandler, ['action' => 'profile', 'u' => null]);
        $editRoute    = new QueryParamRoute('/', $editHandler, ['action' => 'edit', 'u' => null]);

        $this->router->addRoute($profileRoute);
        $this->router->addRoute($editRoute);

        // Create middleware pipeline
        $pipe = new MiddlewarePipe();
        $pipe->pipe($this->createRoutingMiddleware());
        $pipe->pipe($this->createDispatchMiddleware());

        // Test profile action
        $request  = new ServerRequest([], [], '/', 'GET');
        $request  = $request->withQueryParams(['action' => 'profile', 'u' => '1']);
        $response = $pipe->process($request, $this->createFinalHandler());
        static::assertSame('profile-response', (string) $response->getBody());

        // Test edit action
        $request  = new ServerRequest([], [], '/', 'GET');
        $request  = $request->withQueryParams(['action' => 'edit', 'u' => '1']);
        $response = $pipe->process($request, $this->createFinalHandler());
        static::assertSame('edit-response', (string) $response->getBody());

        // Test non-matching action
        $request  = new ServerRequest([], [], '/', 'GET');
        $request  = $request->withQueryParams(['action' => 'delete', 'u' => '1']);
        $response = $pipe->process($request, $this->createFinalHandler());
        static::assertSame('not-found', (string) $response->getBody());
    }

    protected function setUp(): void
    {
        $this->router = new QueryParamRouter(new QueryParamDuplicateRouteDetector());
    }

    private function createDispatchMiddleware(): MiddlewareInterface
    {
        return new class() implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                /** @var RouteResult|null $result */
                $result = $request->getAttribute(RouteResult::class);

                if ($result instanceof RouteResult && $result->isSuccess()) {
                    /** @var Route $route */
                    $route = $result->getMatchedRoute();

                    return $route->process($request, $handler);
                }

                return $handler->handle($request);
            }
        };
    }

    private function createFinalHandler(): RequestHandlerInterface
    {
        return new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('not-found', 404);
            }
        };
    }

    // Helper methods

    private function createHandler(string $responseText): MiddlewareInterface
    {
        return new class($responseText) implements MiddlewareInterface {
            public function __construct(
                private string $responseText,
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return new TextResponse($this->responseText);
            }
        };
    }

    private function createRoutingMiddleware(): MiddlewareInterface
    {
        $router = $this->router;

        return new class($router) implements MiddlewareInterface {
            public function __construct(
                private QueryParamRouter $router,
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $result  = $this->router->match($request);
                $request = $request->withAttribute(RouteResult::class, $result);

                return $handler->handle($request);
            }
        };
    }
}
