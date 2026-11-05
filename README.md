# Query Parameter Router

A query-parameter-based `RouterInterface` implementation for routing requests to middleware/handlers based on `$request->getQueryParams()`. Fully compatible with [mezzio/mezzio-router](https://github.com/mezzio/mezzio-router) and [laminas/laminas-stratigility](https://github.com/laminas/laminas-stratigility).

## Features

- **Query Parameter Matching**: Routes match based on path + required query parameter keys (case-sensitive)
- **HTTP Method Support**: Optional HTTP method filtering (GET, POST, etc.) or match any method
- **Duplicate Detection**: Prevents conflicting routes with the same path + query params + method
- **Most-Specific Matching**: Automatically selects the route with the most specific query parameter requirements
- **URI Generation**: Generate URIs with query parameters via `generateUri()`
- **Laminas Service Manager Integration**: Full autowiring support via ConfigProvider
- **TDD Implementation**: Comprehensive unit and integration test coverage

## Installation

```bash
composer require webware/query-param-router
```

`webware/query-param-router` is currently a pre-release, so the consuming project's root must allow pre-release stability. The Webware application skeleton declares `minimum-stability: dev` with `prefer-stable: true`, which satisfies that, so no stability change is needed. Every runtime dependency of the router is stable, so the router is the only pre-release in the graph.

## Basic Usage

### Simple Route Registration

```php
use Webware\Router\QueryParamRoute;
use Webware\Router\QueryParamRouter;
use Laminas\Diactoros\ServerRequest;

$router = new QueryParamRouter();

// Route requiring 'action' query parameter
$route = new QueryParamRoute(
    '/api',                           // path
    $myMiddleware,                    // middleware/handler
    ['action'],                       // required query param keys
    ['POST'],                         // optional HTTP methods (null = any)
    'api-action'                      // optional route name
);

$router->addRoute($route);

// Match request
$request = new ServerRequest([], [], '/api', 'POST');
$request = $request->withQueryParams(['action' => 'create']);

$result = $router->match($request);

if ($result->isSuccess()) {
    $params = $result->getMatchedParams();
    // $params = ['action' => 'create']
}
```

### Auto-Generated Route Names

If you don't provide a route name, one is generated automatically:

```php
// Route: /api with query params ['action', 'type']
// Generated name: "/api?action&type"

// Route: /api with query params ['action'] and method GET
// Generated name: "/api?action^GET"

// Route: /api with no query params
// Generated name: "/api"
```

### Multiple Routes on Same Path

Different query parameters allow multiple routes on the same path:

```php
// These can coexist:
$router->addRoute(new QueryParamRoute('/api', $handler1, ['action']));
$router->addRoute(new QueryParamRoute('/api', $handler2, ['type']));
$router->addRoute(new QueryParamRoute('/api', $handler3, ['action', 'type']));

// Request with ?action=create matches first route
// Request with ?type=user matches second route  
// Request with ?action=create&type=user matches third route (most specific)
```

### HTTP Method Filtering

```php
// Only match POST requests
$postRoute = new QueryParamRoute('/api', $handler, ['action'], ['POST']);

// Match any HTTP method (default)
$anyRoute = new QueryParamRoute('/api', $handler, ['action']);

// Match multiple methods
$multiRoute = new QueryParamRoute('/api', $handler, ['action'], ['GET', 'POST', 'PUT']);
```

## Duplicate Detection

The router can detect and prevent duplicate routes:

```php
use Webware\Router\QueryParamDuplicateRouteDetector;

$detector = new QueryParamDuplicateRouteDetector();
$router = new QueryParamRouter($detector);

$router->addRoute(new QueryParamRoute('/api', $handler1, ['action']));

// This will throw DuplicateRouteException:
$router->addRoute(new QueryParamRoute('/api', $handler2, ['action']));
```

**Duplicate Detection Rules:**

- Same route name → Duplicate
- Same path + query param keys + HTTP method → Duplicate
- Query param keys are order-independent: `['action', 'type']` == `['type', 'action']`
- Query param keys are case-sensitive: `['Action']` ≠ `['action']`

## URI Generation

Generate URIs with query parameters:

```php
$route = new QueryParamRoute(
    '/users/{id}/posts',
    $handler,
    ['action'],
    null,
    'user-posts'
);

$router->addRoute($route);

// Generate with path substitution and query params
$uri = $router->generateUri(
    'user-posts',
    ['id' => '42'],                           // path substitutions
    ['query' => ['action' => 'edit', 'draft' => 'true']]  // query params
);

// Result: /users/42/posts?action=edit&draft=true
```

## Integration with Laminas Stratigility

Use with middleware pipelines:

```php
use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Router\RouteResult;

$pipe = new MiddlewarePipe();

// Routing middleware
$pipe->pipe(function ($request, $handler) use ($router) {
    $result = $router->match($request);
    $request = $request->withAttribute(RouteResult::class, $result);
    return $handler->handle($request);
});

// Dispatch middleware
$pipe->pipe(function ($request, $handler) {
    $result = $request->getAttribute(RouteResult::class);
    if ($result->isSuccess()) {
        return $result->getMatchedRoute()->process($request, $handler);
    }
    return $handler->handle($request);
});

$response = $pipe->handle($request);
```

## Service Manager Configuration

### Using ConfigProvider (Recommended)

Add to your config aggregator:

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;

$aggregator = new ConfigAggregator([
    \Webware\Router\ConfigProvider::class,
    // ... other providers
]);

return $aggregator->getMergedConfig();
```

### Manual Registration

```php
// config/autoload/dependencies.global.php
use Webware\Router\QueryParamRouter;
use Webware\Router\QueryParamRouterFactory;

return [
    'dependencies' => [
        'factories' => [
            QueryParamRouter::class => QueryParamRouterFactory::class,
        ],
    ],
    QueryParamRouter::class => [
        'detect_duplicates' => true,  // Enable duplicate detection (default)
    ],
];
```

### Retrieve from Container

```php
use Webware\Router\QueryParamRouter;

$router = $container->get(QueryParamRouter::class);
```

## Configuration Options

### Router Configuration

```php
[
    QueryParamRouter::class => [
        'detect_duplicates' => true,  // Enable/disable duplicate detection (default: true)
    ],
]
```

## Matching Behavior

### Case Sensitivity

Query parameter **keys** are **case-sensitive**:

```php
$route = new QueryParamRoute('/api', $handler, ['Action']);

// Matches: ?Action=value
// Does NOT match: ?action=value
```

### Required vs Extra Parameters

Only the **required** query parameter keys must be present. Extra parameters are allowed but ignored:

```php
$route = new QueryParamRoute('/api', $handler, ['action']);

// Matches: ?action=create
// Matches: ?action=create&extra=ignored&another=also-ignored
// Does NOT match: ?other=value (missing 'action')
```

### Empty Query Parameter Routes

Routes with no required query params match any query parameters:

```php
$route = new QueryParamRoute('/api', $handler, []);

// Matches: /api (no query params)
// Matches: /api?any=params&are=fine
```

### Most-Specific Route Wins

When multiple routes match, the one with the **most required query parameters** is selected:

```php
$router->addRoute(new QueryParamRoute('/api', $handler1, ['action']));           // 1 param
$router->addRoute(new QueryParamRoute('/api', $handler2, ['action', 'type']));   // 2 params

$request->withQueryParams(['action' => 'create', 'type' => 'user']);
// Matches second route (more specific)
```

## Matched Parameters

Matched query parameter **values** are included in `RouteResult::getMatchedParams()`:

```php
$route = new QueryParamRoute('/api', $handler, ['action', 'type']);
$result = $router->match($request->withQueryParams(['action' => 'create', 'type' => 'user']));

$params = $result->getMatchedParams();
// ['action' => 'create', 'type' => 'user']
```

Only **required** parameter values are included (extra params are ignored).

## Failure Scenarios

### Missing Required Query Parameter

Returns `RouteResult::fromRouteFailure(null)` (treated as 404):

```php
$route = new QueryParamRoute('/api', $handler, ['action']);
$result = $router->match($request->withQueryParams(['other' => 'value']));

$result->isFailure();  // true
```

### HTTP Method Mismatch

Returns failure when method doesn't match:

```php
$route = new QueryParamRoute('/api', $handler, ['action'], ['POST']);
$result = $router->match($request->withMethod('GET')->withQueryParams(['action' => 'value']));

$result->isFailure();  // true
```

### Path Not Found

Returns failure when path doesn't match any route:

```php
$result = $router->match($request->withUri(new Uri('/unknown')));
$result->isFailure();  // true
```

## Comparison with Standard Mezzio Routing

| Feature | Standard Mezzio Router | Query Param Router |
| --------- | ---------------------- | ------------------- |
| Match by Path | ✅ | ✅ |
| Match by HTTP Method | ✅ | ✅ |
| Match by Query Params | ❌ | ✅ |
| Path Parameters (`{id}`) | ✅ | ✅ (via URI generation) |
| Most-Specific Matching | ❌ | ✅ |
| Duplicate Detection | ✅ (path+method) | ✅ (path+query+method) |

## Development

The toolchain runs in a container, so the host needs no PHP install.

```shell
docker compose up -d
docker compose exec tooling composer install
docker compose exec tooling composer test
docker compose exec tooling composer test-integration
docker compose exec tooling mago lint
docker compose down
```

### Running Tests

```bash
# All tests
docker compose exec tooling composer test-all

# Unit tests only
docker compose exec tooling composer test

# Integration tests only
docker compose exec tooling composer test-integration

# With coverage
docker compose exec tooling composer test-coverage
```

### Code Quality

Four Mago gates must be clean: `fmt --check`, `lint`, `analyze`, `guard`. Mago is invoked
directly as a binary and is never proxied through a composer script.

```bash
docker compose exec tooling mago fmt --check
docker compose exec tooling mago lint
docker compose exec tooling mago analyze
docker compose exec tooling mago guard
```

Mutation testing runs Infection with threads capped at 4:

```bash
docker compose exec tooling composer mutation-test
```

## Requirements

- PHP ~8.4.1 || ~8.5.0
- [mezzio/mezzio-router](https://github.com/mezzio/mezzio-router) ^3.15 || ^4.0
- [laminas/laminas-stratigility](https://github.com/laminas/laminas-stratigility) ^3.10 (for integration)

## License

BSD-3-Clause. See [LICENSE](LICENSE) file.

## Contributing

Contributions welcome! Please ensure all tests pass and follow existing code style.

## Authors

- Joey (aka Tyrsson) Smith <jsmith@webinertia.net>

## Support

- [GitHub Issues](https://github.com/webinertia/query-param-router/issues)
- [GitHub Discussions](https://github.com/webinertia/query-param-router/discussions)
