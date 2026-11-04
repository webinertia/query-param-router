# QueryParamRoute

`Webware\Router\QueryParamRoute` wraps a Mezzio `Route` to add support for query parameter-based matching.

## Class Overview

`Mezzio\Router\Route` is `final`, so `QueryParamRoute` composes one instead of extending it.
Call `toRoute()` to obtain the wrapped route.

```php
namespace Webware\Router;
use Mezzio\Router\Route;
use Psr\Http\Server\MiddlewareInterface;
final class QueryParamRoute implements MiddlewareInterface
{
    public function __construct(
        string $path,
        MiddlewareInterface $middleware,
        array $queryParamKeys = [],
        ?array $methods = self::HTTP_METHOD_ANY,
        ?string $name = null
    );
    public function getQueryParamKeys(): array;
    public function matchesQueryParams(array $queryParams): bool;
    public function toRoute(): Route;
}

```

## Constructor Parameters

### `$path` (string, required)

The URL path to match. Supports path parameters using curly braces.

```php
'/api'              // static path
'/users/{id}'       // path with parameter
'/posts/{id}/edit'  // multiple segments

```

### `$middleware` (MiddlewareInterface, required)

The middleware or request handler to execute when the route matches.

```php
$middleware = new MyActionMiddleware();
$route = new QueryParamRoute('/api', $middleware, ['action']);

```

### `$queryParamKeys` (array, optional, default: `[]`)

Array of query parameter keys required for this route to match. Keys are **case-sensitive**.

```php
['action']                    // requires ?action=X
['board', 'topic']           // requires ?board=X&topic=Y
['user_id', 'action']        // requires ?user_id=X&action=Y
[]                           // no query params required (matches any)

```

**Important**: Only the **presence** of keys matters, not their values. Any value is accepted.

### `$methods` (array|null, optional, default: `null`)

HTTP methods allowed for this route. `null` means any method is allowed.

```php
['GET']                      // GET only
['POST', 'PUT']             // POST or PUT
['GET', 'POST', 'DELETE']   // multiple methods
null                        // any method (default)

```

### `$name` (string|null, optional, default: auto-generated)

Route name for URI generation. If not provided, a name is auto-generated.

```php
'api.users.create'          // explicit name
null                        // auto-generated

```

## Auto-Generated Route Names

When `$name` is `null`, the route name is automatically generated using this format:

| Configuration | Generated Name |
| -------------- | ---------------- |
| `/api` + no query params + any method | `/api` |
| `/api` + `['action']` + any method | `/api?action` |
| `/api` + `['action', 'type']` + any method | `/api?action&type` |
| `/api` + `['action']` + `['GET']` | `/api?action^GET` |
| `/api` + `['action', 'type']` + `['GET', 'POST']` | `/api?action&type^GET:POST` |

## Methods

### `getQueryParamKeys(): array`

Returns the array of required query parameter keys.

```php
$route = new QueryParamRoute('/api', $middleware, ['action', 'type']);
$keys = $route->getQueryParamKeys();
// ['action', 'type']

```

### `matchesQueryParams(array $queryParams): bool`

Checks if the given query parameters satisfy the route's requirements.

```php
$route = new QueryParamRoute('/api', $middleware, ['action']);
$route->matchesQueryParams(['action' => 'create']);
// true
$route->matchesQueryParams(['action' => 'create', 'extra' => 'ignored']);
// true (extra params are allowed)
$route->matchesQueryParams(['other' => 'value']);
// false (missing 'action')
$route->matchesQueryParams([]);
// false (missing 'action')

```

## Usage Examples

### Basic Route

```php
use Webware\Router\QueryParamRoute;
$route = new QueryParamRoute(
    '/forum',
    $displayBoardMiddleware,
    ['board']
);
// Matches: /forum?board=1
// Matches: /forum?board=1&page=2 (extra params OK)
// Does NOT match: /forum (missing 'board')

```

### Route with HTTP Method

```php
$route = new QueryParamRoute(
    '/api',
    $createUserMiddleware,
    ['action'],
    ['POST']
);
// Matches: POST /api?action=create
// Does NOT match: GET /api?action=create (wrong method)

```

### Route with Multiple Query Parameters

```php
$route = new QueryParamRoute(
    '/forum',
    $displayTopicMiddleware,
    ['board', 'topic']
);
// Matches: /forum?board=1&topic=100
// Matches: /forum?topic=100&board=1 (order doesn't matter)
// Does NOT match: /forum?board=1 (missing 'topic')

```

### Route with Path Parameters

```php
$route = new QueryParamRoute(
    '/users/{id}/posts',
    $userPostsMiddleware,
    ['action'],
    null,
    'user.posts'
);
// Path substitution happens during URI generation
$router->generateUri('user.posts', ['id' => '42'], ['query' => ['action' => 'list']]);
// Result: /users/42/posts?action=list

```

### Route with Explicit Name

```php
$route = new QueryParamRoute(
    '/api',
    $middleware,
    ['action'],
    ['POST'],
    'api.create'  // explicit name
);
// Use this name for URI generation
$router->generateUri('api.create', [], ['query' => ['action' => 'user']]);

```

### Route Matching Any Query Parameters

```php
$route = new QueryParamRoute(
    '/api',
    $middleware,
    []  // empty array = no required params
);
// Matches: /api
// Matches: /api?anything=goes
// Matches: /api?foo=bar&baz=qux

```

## Case Sensitivity

Query parameter keys are **case-sensitive**:

```php
$route = new QueryParamRoute('/api', $middleware, ['Action']);
// Matches: ?Action=value
// Does NOT match: ?action=value

```

## Value Matching

The router only checks for the **presence of keys**, not their values. Any value is accepted:

```php
$route = new QueryParamRoute('/', $middleware, ['board']);
// All of these match:
// ?board=1
// ?board=100.2
// ?board=general
// ?board=
// ?board[]=multiple&board[]=values

```

## Storage in Route Options

Query parameter keys are automatically stored in the route's options array under the `query_params` key:

```php
$route = new QueryParamRoute('/api', $middleware, ['action', 'type']);
$options = $route->getOptions();
// $options['query_params'] = ['action', 'type']

```

This allows the router to access query parameter requirements even for standard `Route` objects.

## Composition

`Mezzio\Router\Route` is `final`, so `QueryParamRoute` composes one and proxies the standard route
surface. Call `toRoute()` when you need the wrapped `Route` itself:

```php
// Proxied standard Route methods work directly
$route->getPath();              // '/api'
$route->getName();              // 'api-route' or auto-generated
$route->getMiddleware();        // MiddlewareInterface
$route->getAllowedMethods();    // ['GET', 'POST'] or null
$route->allowsMethod('GET');    // true/false
$route->allowsAnyMethod();      // true/false
$route->process($request, $handler);  // Execute middleware

// The wrapped Mezzio route
$route->toRoute();              // Mezzio\Router\Route

```

## Best Practices

### 1. Use Explicit Names for Important Routes

```php
// Good: explicit name for URI generation
new QueryParamRoute('/api', $handler, ['action'], ['POST'], 'api.create');
// OK: auto-generated for simple routes
new QueryParamRoute('/health', $handler, []);

```

### 2. Order Query Keys Consistently

While order doesn't affect matching, consistent ordering improves readability:

```php
// Good: alphabetical order
new QueryParamRoute('/forum', $handler, ['board', 'topic']);
// Less clear
new QueryParamRoute('/forum', $handler, ['topic', 'board']);

```

### 3. Document Required Parameters

```php
/**
 * Forum topic display route
 *
 * Required query parameters:
 * - board (int): Board ID
 * - topic (int): Topic ID
 *
 * Optional query parameters:
 * - page (int): Page number
 */
$route = new QueryParamRoute('/forum', $displayTopicHandler, ['board', 'topic']);

```

### 4. Use HTTP Methods for Actions

```php
// Good: method indicates action
new QueryParamRoute('/api', $createHandler, ['resource'], ['POST']);
new QueryParamRoute('/api', $updateHandler, ['resource'], ['PUT']);
new QueryParamRoute('/api', $deleteHandler, ['resource'], ['DELETE']);
// Less clear: action in query param
new QueryParamRoute('/api', $handler, ['action', 'resource']);

```

## See Also

- [QueryParamRouter](QueryParamRouter.md) - Router implementation
- [QueryParamDuplicateRouteDetector](QueryParamDuplicateRouteDetector.md) - Duplicate detection
- [Examples](Examples.md) - Real-world usage patterns
