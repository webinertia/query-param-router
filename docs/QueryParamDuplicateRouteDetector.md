# QueryParamDuplicateRouteDetector

`Webware\Router\QueryParamDuplicateRouteDetector` detects and prevents duplicate routes based on path, query parameter keys, and HTTP methods.

## Class Overview

```php
namespace Webware\Router;
use Mezzio\Router\Route;
use Mezzio\Router\Exception\DuplicateRouteException;
final class QueryParamDuplicateRouteDetector
{
    public function __construct();
    public function detectDuplicate(Route $route): void;
}

```

## Purpose

Prevents route conflicts by ensuring that no two routes share:

- Same route name, OR
- Same path + query parameter keys + HTTP method combination

## Method

### `detectDuplicate(Route $route): void`

Checks if the given route conflicts with previously registered routes.
**Throws**: `DuplicateRouteException` if a duplicate is detected.

```php
$detector = new QueryParamDuplicateRouteDetector();
$route1 = new QueryParamRoute('/api', $handler1, ['action']);
$detector->detectDuplicate($route1); // OK
$route2 = new QueryParamRoute('/api', $handler2, ['action']);
$detector->detectDuplicate($route2); // Throws DuplicateRouteException

```

## Duplicate Detection Rules

### 1. By Route Name

Routes with the same name are always duplicates:

```php
$route1 = new QueryParamRoute('/api', $handler1, ['action'], null, 'my-route');
$route2 = new QueryParamRoute('/other', $handler2, ['type'], null, 'my-route');
$detector->detectDuplicate($route1); // OK
$detector->detectDuplicate($route2); // Throws: duplicate name "my-route"

```

### 2. By Path + Query Parameters + HTTP Method

Routes are duplicates if they share the same:

- Path (exact match)
- Query parameter keys (order-independent)
- HTTP method

```php
// These are duplicates:
$route1 = new QueryParamRoute('/api', $handler1, ['action'], ['GET']);
$route2 = new QueryParamRoute('/api', $handler2, ['action'], ['GET']);
// These are NOT duplicates (different methods):
$route1 = new QueryParamRoute('/api', $handler1, ['action'], ['GET']);
$route2 = new QueryParamRoute('/api', $handler2, ['action'], ['POST']);
// These are NOT duplicates (different query params):
$route1 = new QueryParamRoute('/api', $handler1, ['action']);
$route2 = new QueryParamRoute('/api', $handler2, ['type']);

```

## Query Parameter Key Matching

### Order Independent

Query parameter keys can be in any order:

```php
$route1 = new QueryParamRoute('/api', $handler1, ['action', 'type']);
$route2 = new QueryParamRoute('/api', $handler2, ['type', 'action']);
$detector->detectDuplicate($route1); // OK
$detector->detectDuplicate($route2); // Throws: duplicate (same keys, different order)

```

### Case Sensitive

Query parameter keys are case-sensitive:

```php
$route1 = new QueryParamRoute('/api', $handler1, ['Action']);
$route2 = new QueryParamRoute('/api', $handler2, ['action']);
$detector->detectDuplicate($route1); // OK
$detector->detectDuplicate($route2); // OK (different case = different keys)

```

## HTTP Method Behavior

### Any Method Routes

Routes that accept any HTTP method conflict with all other routes on the same path + query params:

```php
$route1 = new QueryParamRoute('/api', $handler1, ['action']); // any method
$route2 = new QueryParamRoute('/api', $handler2, ['action'], ['GET']);
$detector->detectDuplicate($route1); // OK
$detector->detectDuplicate($route2); // Throws: route1 already accepts GET
```

```php
$route1 = new QueryParamRoute('/api', $handler1, ['action'], ['GET']);
$route2 = new QueryParamRoute('/api', $handler2, ['action']); // any method
$detector->detectDuplicate($route1); // OK
$detector->detectDuplicate($route2); // Throws: conflicts with GET route

```

### Specific Method Overlap

Routes with overlapping methods are duplicates:

```php
$route1 = new QueryParamRoute('/api', $handler1, ['action'], ['GET', 'POST']);
$route2 = new QueryParamRoute('/api', $handler2, ['action'], ['POST', 'PUT']);
$detector->detectDuplicate($route1); // OK
$detector->detectDuplicate($route2); // Throws: POST method overlaps

```

### Non-Overlapping Methods

Routes with completely different methods can coexist:

```php
$route1 = new QueryParamRoute('/api', $handler1, ['action'], ['GET']);
$route2 = new QueryParamRoute('/api', $handler2, ['action'], ['POST']);
$route3 = new QueryParamRoute('/api', $handler3, ['action'], ['PUT', 'DELETE']);
$detector->detectDuplicate($route1); // OK
$detector->detectDuplicate($route2); // OK
$detector->detectDuplicate($route3); // OK

```

## Empty Query Parameters

Routes with no query parameters are treated as a separate category:

```php
$route1 = new QueryParamRoute('/api', $handler1, []);
$route2 = new QueryParamRoute('/api', $handler2, []);
$detector->detectDuplicate($route1); // OK
$detector->detectDuplicate($route2); // Throws: both have no query params
// But these don't conflict:
$route1 = new QueryParamRoute('/api', $handler1, []);
$route2 = new QueryParamRoute('/api', $handler2, ['action']);
// OK: different query param requirements

```

## Exception Details

When a duplicate is detected, `DuplicateRouteException` is thrown with a descriptive message:

```php
try {
    $detector->detectDuplicate($route);
} catch (DuplicateRouteException $e) {
    echo $e->getMessage();
    // Duplicate route detected; path "/api" with query params [action]
    // answering to methods [GET], with name "api-route"
}

```

Exception message includes:

- Path
- Query parameter keys (if any)
- HTTP methods
- Route name (if set)

## Usage Examples

### Basic Usage

```php
use Webware\Router\QueryParamDuplicateRouteDetector;
use Webware\Router\QueryParamRoute;
$detector = new QueryParamDuplicateRouteDetector();
// Register routes
$routes = [
    new QueryParamRoute('/api', $createHandler, ['action'], ['POST']),
    new QueryParamRoute('/api', $listHandler, ['action'], ['GET']),
    new QueryParamRoute('/api', $detailHandler, ['action', 'id'], ['GET']),
];
foreach ($routes as $route) {
    try {
        $detector->detectDuplicate($route);
        // Route is unique, can be added
    } catch (DuplicateRouteException $e) {
        // Handle duplicate: log, skip, or throw
        error_log($e->getMessage());
    }
}

```

### With Router

```php
use Webware\Router\QueryParamRouter;
use Webware\Router\QueryParamDuplicateRouteDetector;
$detector = new QueryParamDuplicateRouteDetector();
$router = new QueryParamRouter($detector);
// Duplicate detection happens automatically on addRoute()
try {
    $router->addRoute($route1);
    $router->addRoute($route2); // May throw if duplicate
} catch (DuplicateRouteException $e) {
    // Handle duplicate
}

```

### Development vs Production

```php
// Development: Enable for early error detection
if ($isDevelopment) {
    $detector = new QueryParamDuplicateRouteDetector();
    $router = new QueryParamRouter($detector);
} else {
    // Production: Disable for slight performance gain
    $router = new QueryParamRouter(null);
}

```

### Validation During Route Registration

```php
class RouteRegistrar
{
    private QueryParamDuplicateRouteDetector $detector;
    private array $registeredRoutes = [];
    public function __construct()
    {
        $this->detector = new QueryParamDuplicateRouteDetector();
    }
    public function register(Route $route): void
    {
        try {
            $this->detector->detectDuplicate($route);
            $this->registeredRoutes[] = $route;
        } catch (DuplicateRouteException $e) {
            throw new \RuntimeException(
                "Cannot register route: " . $e->getMessage()
            );
        }
    }
    public function getRoutes(): array
    {
        return $this->registeredRoutes;
    }
}

```

## Testing Routes for Conflicts

```php
use PHPUnit\Framework\TestCase;
use Webware\Router\QueryParamDuplicateRouteDetector;
use Mezzio\Router\Exception\DuplicateRouteException;
class RouteConfigTest extends TestCase
{
    public function testNoRouteDuplicates(): void
    {
        $detector = new QueryParamDuplicateRouteDetector();
        $routes = $this->loadRouteConfiguration();
        foreach ($routes as $route) {
            $detector->detectDuplicate($route);
        }
        // If we get here, no duplicates were found
        $this->assertTrue(true);
    }
    public function testDetectsDuplicateRoutes(): void
    {
        $detector = new QueryParamDuplicateRouteDetector();
        $route1 = new QueryParamRoute('/api', $handler1, ['action']);
        $route2 = new QueryParamRoute('/api', $handler2, ['action']);
        $detector->detectDuplicate($route1);
        $this->expectException(DuplicateRouteException::class);
        $detector->detectDuplicate($route2);
    }
}

```

## Best Practices

### 1. Always Enable During Development

```php
// config/autoload/development.local.php
return [
    'router' => [
        'detect_duplicates' => true,  // Always true in development
    ],
];

```

### 2. Consider Disabling in Production

```php
// config/autoload/production.local.php
return [
    'router' => [
        'detect_duplicates' => false,  // Optional: disable for performance
    ],
];

```

**Note**: Duplicate detection has minimal performance impact since it only runs during route registration, not on each request.

### 3. Test Route Configuration

Include tests that verify no duplicates exist in your route configuration:

```php
public function testRouteConfigurationHasNoDuplicates(): void
{
    $detector = new QueryParamDuplicateRouteDetector();
    $config = require 'config/routes.php';
    foreach ($config as $routeConfig) {
        $route = $this->createRouteFromConfig($routeConfig);
        $detector->detectDuplicate($route);
    }
    $this->addToAssertionCount(1);
}

```

### 4. Handle Duplicates Gracefully

```php
foreach ($routes as $route) {
    try {
        $detector->detectDuplicate($route);
        $router->addRoute($route);
    } catch (DuplicateRouteException $e) {
        // Log for debugging but don't crash in production
        $logger->warning('Duplicate route detected', [
            'message' => $e->getMessage(),
            'route' => $route->getName(),
        ]);
    }
}

```

## Internal Data Structure

The detector maintains routes in a nested structure for efficient lookup:

```php
[
    '/path' => [
        'querykey1,querykey2' => [  // sorted keys
            'any' => $route,        // route accepting any method
            'methods' => [
                'GET' => $route,
                'POST' => $route,
            ],
        ],
    ],
]

```

This structure allows O(1) duplicate detection.

## See Also

- [QueryParamRouter](QueryParamRouter.md) - Router using the detector
- [QueryParamRoute](QueryParamRoute.md) - Route definition
- [Configuration](Configuration.md) - Enabling/disabling detection
