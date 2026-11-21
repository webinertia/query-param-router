# QueryParamRouter

`Webware\Router\QueryParamRouter` is the main router implementation that matches requests based on path, query parameters, and HTTP methods.

## Class Overview

```php
namespace Webware\Router;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use Psr\Http\Message\ServerRequestInterface;
class QueryParamRouter implements RouterInterface
{
    public function __construct(
        ?QueryParamDuplicateRouteDetector $duplicateDetector = null
    );
    public function addRoute(Route $route): void;
    public function match(ServerRequestInterface $request): RouteResult;
    public function generateUri(
        string $name,
        array $substitutions = [],
        array $options = []
    ): string;
}

```

## Constructor

### `$duplicateDetector` (QueryParamDuplicateRouteDetector|null)

Optional duplicate route detector. When provided, duplicate routes will throw a `DuplicateRouteException`.

```php
// With duplicate detection (recommended)
$detector = new QueryParamDuplicateRouteDetector();
$router = new QueryParamRouter($detector);
// Without duplicate detection
$router = new QueryParamRouter(null);

```

## Methods

### `addRoute(Route $route): void`

Adds a route to the router. Routes are stored but not injected into any underlying router until `match()` or `generateUri()` is called (per `RouterInterface` contract).

```php
$route = new QueryParamRoute('/api', $middleware, ['action']);
$router->addRoute($route);

```

**Throws**: `DuplicateRouteException` if duplicate detector is enabled and a duplicate is found.
Works with both `QueryParamRoute` and standard `Route` objects. For standard routes, query parameters can be set via the route's options:

```php
$route = new Route('/api', $middleware);
$route->setOptions(['query_params' => ['action']]);
$router->addRoute($route);

```

### `match(ServerRequestInterface $request): RouteResult`

Matches a request against registered routes and returns a `RouteResult`.

```php
$request = new ServerRequest([], [], '/api', 'GET');
$request = $request->withQueryParams(['action' => 'create']);
$result = $router->match($request);
if ($result->isSuccess()) {
    $route = $result->getMatchedRoute();
    $params = $result->getMatchedParams();
    // ['action' => 'create']
}

```

#### Matching Algorithm

1. **Path Matching**: Find all routes with matching path
2. **Query Parameter Matching**: Filter routes where all required query param keys are present
3. **HTTP Method Matching**: Filter routes that allow the request method
4. **Most-Specific Selection**: Select route with most required query parameters

#### Success Result

```php
$result->isSuccess();              // true
$result->getMatchedRoute();        // Route object
$result->getMatchedParams();       // ['key' => 'value', ...]
$result->getMatchedRouteName();    // 'route-name'
$result->getAllowedMethods();      // ['GET', 'POST']

```

#### Failure Result

```php
$result->isFailure();              // true
$result->getMatchedRoute();        // false
$result->getAllowedMethods();      // null

```

### `generateUri(string $name, array $substitutions = [], array $options = []): string`

Generates a URI from a named route.

```php
$uri = $router->generateUri('api-route');
// /api
$uri = $router->generateUri('user-route', ['id' => '42']);
// /users/42
$uri = $router->generateUri('api-route', [], ['query' => ['action' => 'create']]);
// /api?action=create
$uri = $router->generateUri('user-route', ['id' => '42'], ['query' => ['action' => 'edit']]);
// /users/42?action=edit

```

#### Parameters

- **`$name`**: Route name
- **`$substitutions`**: Path parameter substitutions (e.g., `['id' => '42']` for `/users/{id}`)
- **`$options['query']`**: Query parameters to append (e.g., `['action' => 'create']`)
**Throws**: `RuntimeException` if the route name is not found.
**Returns**: Unescaped URI string (per `RouterInterface` contract).

## Usage Examples

### Basic Routing

```php
use Webware\Router\QueryParamRoute;
use Webware\Router\QueryParamRouter;
$router = new QueryParamRouter();
// Add route
$route = new QueryParamRoute('/api', $middleware, ['action']);
$router->addRoute($route);
// Match request
$request = $request->withQueryParams(['action' => 'create']);
$result = $router->match($request);
if ($result->isSuccess()) {
    $params = $result->getMatchedParams();
    // ['action' => 'create']
}

```

### With Duplicate Detection

```php
use Webware\Router\QueryParamDuplicateRouteDetector;
use Webware\Router\QueryParamRouter;
$detector = new QueryParamDuplicateRouteDetector();
$router = new QueryParamRouter($detector);
$router->addRoute(new QueryParamRoute('/api', $handler1, ['action']));
try {
    // This throws DuplicateRouteException
    $router->addRoute(new QueryParamRoute('/api', $handler2, ['action']));
} catch (DuplicateRouteException $e) {
    // Handle duplicate
}

```

### Multiple Routes on Same Path

```php
// Different query parameters
$router->addRoute(new QueryParamRoute('/api', $handler1, ['action']));
$router->addRoute(new QueryParamRoute('/api', $handler2, ['type']));
$router->addRoute(new QueryParamRoute('/api', $handler3, ['action', 'type']));
// Request with ?action=create matches first route
// Request with ?type=user matches second route
// Request with ?action=create&type=user matches third route (most specific)

```

### HTTP Method Filtering

```php
$router->addRoute(new QueryParamRoute('/api', $createHandler, ['resource'], ['POST']));
$router->addRoute(new QueryParamRoute('/api', $listHandler, ['resource'], ['GET']));
// POST /api?resource=users → createHandler
// GET /api?resource=users → listHandler

```

### Most-Specific Route Selection

```php
$router->addRoute(new QueryParamRoute('/forum', $displayBoard, ['board']));
$router->addRoute(new QueryParamRoute('/forum', $displayTopic, ['board', 'topic']));
$router->addRoute(new QueryParamRoute('/forum', $displayPost, ['board', 'topic', 'msg']));
// /forum?board=1 → displayBoard (1 param)
// /forum?board=1&topic=100 → displayTopic (2 params)
// /forum?board=1&topic=100&msg=5 → displayPost (3 params) [most specific]

```

### URI Generation

```php
$route = new QueryParamRoute('/users/{id}', $handler, ['action'], null, 'user-action');
$router->addRoute($route);
// Simple path
$uri = $router->generateUri('user-action', ['id' => '42']);
// /users/42
// With query parameters
$uri = $router->generateUri('user-action', ['id' => '42'], [
    'query' => ['action' => 'edit', 'tab' => 'profile']
]);
// /users/42?action=edit&tab=profile

```

## Matching Behavior Details

### Query Parameter Matching

- **Case-Sensitive**: `board` ≠ `Board`
- **Key-Only**: Only key presence matters, values are not validated
- **Extra Parameters Allowed**: Additional query params don't prevent matching
- **Empty Array Matches All**: Routes with `[]` match any query params

```php
$route = new QueryParamRoute('/api', $handler, ['action']);
// Matches
$router->match($request->withQueryParams(['action' => 'create']));
$router->match($request->withQueryParams(['action' => 'create', 'extra' => 'ok']));
// Does NOT match
$router->match($request->withQueryParams(['Action' => 'create'])); // wrong case
$router->match($request->withQueryParams(['other' => 'value']));   // missing 'action'

```

### Path Matching

Paths must match exactly (case-sensitive):

```php
$route = new QueryParamRoute('/api', $handler, ['action']);
// Matches: /api?action=create
// Does NOT match: /API?action=create
// Does NOT match: /api/?action=create (trailing slash)

```

### HTTP Method Matching

When methods are specified, the request method must match:

```php
$route = new QueryParamRoute('/api', $handler, ['action'], ['POST', 'PUT']);
// Matches: POST /api?action=create
// Matches: PUT /api?action=create
// Does NOT match: GET /api?action=create
// Does NOT match: DELETE /api?action=create

```

### Matched Parameters

Only **required** query parameter values are included in matched params:

```php
$route = new QueryParamRoute('/api', $handler, ['action']);
$request = $request->withQueryParams([
    'action' => 'create',
    'extra' => 'ignored'
]);
$result = $router->match($request);
$params = $result->getMatchedParams();
// ['action' => 'create']
// Note: 'extra' is NOT included

```

## Integration Patterns

### With Laminas Stratigility

```php
use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Router\RouteResult;
$pipe = new MiddlewarePipe();
// Routing middleware
$pipe->pipe(new class($router) implements MiddlewareInterface {
    public function __construct(private QueryParamRouter $router) {}
    public function process($request, $handler): ResponseInterface {
        $result = $this->router->match($request);
        return $handler->handle(
            $request->withAttribute(RouteResult::class, $result)
        );
    }
});
// Dispatch middleware
$pipe->pipe(new class implements MiddlewareInterface {
    public function process($request, $handler): ResponseInterface {
        $result = $request->getAttribute(RouteResult::class);
        if ($result->isSuccess()) {
            return $result->getMatchedRoute()->process($request, $handler);
        }
        return $handler->handle($request);
    }
});

```

### With Service Manager

```php
use Laminas\ServiceManager\ServiceManager;
use Webware\Router\ConfigProvider;
$config = (new ConfigProvider())();
$container = new ServiceManager($config['dependencies']);
$router = $container->get(QueryParamRouter::class);

```

### Error Handling

```php
$result = $router->match($request);
if ($result->isFailure()) {
    // Route not found or method not allowed
    return new Response\EmptyResponse(404);
}
// Process matched route
return $result->getMatchedRoute()->process($request, $handler);

```

## Performance Considerations

### Route Indexing

Routes are indexed by path for O(1) lookup:

```php
// Internally:
// $routesByPath['/api'] = [route1, route2, route3]

```

### Best Practices

1. **Use duplicate detection** during development, disable in production if needed
2. **Order routes** from most specific to least specific (though router handles this)
3. **Use named routes** for URI generation to avoid hardcoding paths
4. **Cache route configuration** in production

## Error Conditions

### DuplicateRouteException

Thrown when duplicate detector is enabled and a duplicate route is added:

```php
try {
    $router->addRoute($duplicate);
} catch (DuplicateRouteException $e) {
    // Handle: log, return error, etc.
}

```

### RuntimeException (generateUri)

Thrown when attempting to generate URI for unknown route:

```php
try {
    $uri = $router->generateUri('unknown-route');
} catch (RuntimeException $e) {
    // Handle: use default URI, log error, etc.
}

```

## See Also

- [QueryParamRoute](QueryParamRoute.md) - Route definition
- [QueryParamDuplicateRouteDetector](QueryParamDuplicateRouteDetector.md) - Duplicate detection
- [Configuration](Configuration.md) - Service Manager setup
- [Examples](Examples.md) - Real-world patterns
