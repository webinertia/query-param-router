# Query Parameter Router Documentation

Welcome to the comprehensive documentation for Query Parameter Router, a query-parameter-based routing implementation for Mezzio and Laminas applications.

## Quick Start

### Installation

```bash
composer require webware/query-param-router

```

### Basic Usage

```php
use Webware\Router\QueryParamRoute;
use Webware\Router\QueryParamRouter;
$router = new QueryParamRouter();
// Create a route that requires 'action' query parameter
$route = new QueryParamRoute(
    '/api',           // path
    $middleware,      // your middleware/handler
    ['action']        // required query param keys
);
$router->addRoute($route);
// Match incoming requests
$result = $router->match($request);

```

## Core Concepts

The Query Parameter Router extends standard Mezzio routing by adding support for matching routes based on query parameter keys. This is useful for:

- **Legacy URL routing** (e.g., `index.php?board=1&action=display`)
- **API versioning via query params**
- **Action-based routing** (e.g., `?action=create`, `?action=list`)
- **Multi-tenant applications** via query parameters

### Key Features

- ✅ Match routes by query parameter keys (case-sensitive)
- ✅ Optional HTTP method filtering
- ✅ Automatic most-specific route selection
- ✅ Duplicate route detection
- ✅ URI generation with query parameters
- ✅ Full Mezzio/Laminas integration
- ✅ PSR-7 and PSR-15 compliant

## Documentation Index

### Core Components

- **[QueryParamRoute](QueryParamRoute.md)** - Route definition with query parameter support
- **[QueryParamRouter](QueryParamRouter.md)** - Main router implementation
- **[QueryParamDuplicateRouteDetector](QueryParamDuplicateRouteDetector.md)** - Duplicate route detection

### Configuration & Integration

- **[ConfigProvider](ConfigProvider.md)** - Service Manager configuration
- **[Configuration Guide](Configuration.md)** - Complete configuration options
- **[Examples](Examples.md)** - Real-world usage examples

## Architecture Overview

```text
Request → QueryParamRouter → Match by:
                              1. Path
                              2. Query Param Keys
                              3. HTTP Method
                              ↓
                           RouteResult → Middleware → Response

```

### Matching Logic

1. **Path Matching**: Request path must match route path exactly
2. **Query Parameter Matching**: All required query param keys must be present (case-sensitive)
3. **HTTP Method Matching**: If specified, request method must match allowed methods
4. **Most-Specific Selection**: Route with most required query params wins

## Basic Example

```php
use Webware\Router\QueryParamRoute;
use Webware\Router\QueryParamRouter;
use Webware\Router\QueryParamDuplicateRouteDetector;
// Create router with duplicate detection
$detector = new QueryParamDuplicateRouteDetector();
$router = new QueryParamRouter($detector);
// Add routes
$router->addRoute(new QueryParamRoute(
    '/forum',
    $displayBoardMiddleware,
    ['board'],           // requires ?board=X
    ['GET'],
    'forum.board'
));
$router->addRoute(new QueryParamRoute(
    '/forum',
    $displayTopicMiddleware,
    ['board', 'topic'],  // requires ?board=X&topic=Y
    ['GET'],
    'forum.topic'
));
// Match request: /forum?board=1&topic=100
$result = $router->match($request);
// Matches second route (more specific)
$params = $result->getMatchedParams();
// ['board' => '1', 'topic' => '100']

```

## Integration with Mezzio/Laminas

### Service Manager Setup

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;
use Webware\Router\ConfigProvider;
$aggregator = new ConfigAggregator([
    ConfigProvider::class,
    // ... other providers
]);
return $aggregator->getMergedConfig();

```

### Using in Middleware Pipeline

```php
use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Router\RouteResult;
use Webware\Router\QueryParamRouter;
$pipe = new MiddlewarePipe();
// Routing middleware
$pipe->pipe(function ($request, $handler) use ($container) {
    $router = $container->get(QueryParamRouter::class);
    $result = $router->match($request);
    return $handler->handle(
        $request->withAttribute(RouteResult::class, $result)
    );
});
// Dispatch middleware
$pipe->pipe(function ($request, $handler) {
    $result = $request->getAttribute(RouteResult::class);
    if ($result->isSuccess()) {
        return $result->getMatchedRoute()->process($request, $handler);
    }
    return $handler->handle($request);
});

```

## Key Behaviors

### Query Parameter Matching

- **Keys are case-sensitive**: `board` ≠ `Board`
- **Only required keys matter**: Extra query params are allowed
- **Values are preserved**: Any value format is supported
- **Empty array matches all**: `[]` means no required params

### Route Priority

When multiple routes match, the route with the **most required query parameters** is selected:

```php
// Given these routes:
Route 1: /api + ['action']           // 1 param
Route 2: /api + ['action', 'type']   // 2 params
// Request: /api?action=create&type=user
// Matches: Route 2 (more specific)

```

### Duplicate Detection

Routes are duplicates if they share:

- Same route name, OR
- Same path + query param keys + HTTP method

```php
// These are duplicates (will throw exception):
Route 1: /api + ['action'] + GET
Route 2: /api + ['action'] + GET
// These are NOT duplicates:
Route 1: /api + ['action'] + GET
Route 2: /api + ['action'] + POST

```

## Common Patterns

### Legacy SMF Routing

```php
// SMF URLs like: index.php?board=1&action=display
$route = new QueryParamRoute(
    '/index.php',
    $handler,
    ['board', 'action']
);

```

### Action-Based Routing

```php
// Different actions on same path
$router->addRoute(new QueryParamRoute('/api', $createHandler, ['action'], ['POST']));
$router->addRoute(new QueryParamRoute('/api', $listHandler, ['action'], ['GET']));

```

### Multi-Tenant Applications

```php
// Tenant routing
$route = new QueryParamRoute(
    '/',
    $tenantMiddleware,
    ['tenant_id']
);

```

## Next Steps

- Read the [QueryParamRoute documentation](QueryParamRoute.md) to learn about route configuration
- Check out [Configuration Guide](Configuration.md) for service manager setup
- Browse [Examples](Examples.md) for real-world use cases
- Review the [QueryParamRouter API](QueryParamRouter.md) for advanced features

## Support & Contributing

- **GitHub Issues**: [webinertia/query-param-router/issues](https://github.com/webinertia/query-param-router/issues)
- **Discussions**: [webinertia/query-param-router/discussions](https://github.com/webinertia/query-param-router/discussions)
Contributions welcome! Please ensure all tests pass and follow the existing code style.
