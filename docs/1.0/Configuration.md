# Configuration Guide

Complete configuration reference for Query Parameter Router with Laminas Service Manager and Mezzio applications.

## Table of Contents

- [Basic Configuration](#basic-configuration)
- [Service Manager Configuration](#service-manager-configuration)
- [Router Options](#router-options)
- [Environment-Specific Configuration](#environment-specific-configuration)
- [Advanced Configuration](#advanced-configuration)

## Basic Configuration

### Using ConfigProvider (Recommended)

Add the ConfigProvider to your configuration aggregator:

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

### Manual Configuration

Without config aggregator, manually merge the configuration:

```php
// config/autoload/dependencies.global.php
use Webware\Router\QueryParamRouter;
use Webware\Router\QueryParamRouterFactory;
use Webware\Router\QueryParamDuplicateRouteDetector;
use Webware\Router\QueryParamDuplicateRouteDetectorFactory;
return [
    'dependencies' => [
        'factories' => [
            QueryParamRouter::class => QueryParamRouterFactory::class,
            QueryParamDuplicateRouteDetector::class => QueryParamDuplicateRouteDetectorFactory::class,
        ],
    ],
];

```

## Service Manager Configuration

### Factory Registration

The router and detector are registered as factories:

```php
'dependencies' => [
    'factories' => [
        QueryParamRouter::class => QueryParamRouterFactory::class,
        QueryParamDuplicateRouteDetector::class => QueryParamDuplicateRouteDetectorFactory::class,
    ],
]

```

### Retrieving from Container

```php
use Webware\Router\QueryParamRouter;
// Via container
$router = $container->get(QueryParamRouter::class);
// Via factory
$factory = new QueryParamRouterFactory();
$router = $factory($container);

```

### Making Router the Default

Alias `RouterInterface` to use QueryParamRouter as default:

```php
use Mezzio\Router\RouterInterface;
use Webware\Router\QueryParamRouter;
return [
    'dependencies' => [
        'aliases' => [
            RouterInterface::class => QueryParamRouter::class,
        ],
    ],
];

```

## Router Options

### Configuration Key

Router options are stored under `QueryParamRouter::class`:

```php
use Webware\Router\QueryParamRouter;
return [
    QueryParamRouter::class => [
        'detect_duplicates' => true,
    ],
];

```

### Available Options

#### `detect_duplicates` (bool)

**Default**: `true`
Enable or disable duplicate route detection.

```php
QueryParamRouter::class => [
    'detect_duplicates' => true,   // Enable (recommended)
    'detect_duplicates' => false,  // Disable
]

```

**When to enable:**

- During development (catch configuration errors early)
- In testing environments
- When route configuration changes frequently
**When to disable:**
- In production (optional, minimal performance impact)
- When routes are static and well-tested
- When using cached configuration

## Environment-Specific Configuration

### Development Environment

```php
// config/autoload/development.local.php
use Webware\Router\QueryParamRouter;
return [
    QueryParamRouter::class => [
        'detect_duplicates' => true,  // Always enabled in dev
    ],
];

```

### Production Environment

```php
// config/autoload/production.local.php
use Webware\Router\QueryParamRouter;
return [
    QueryParamRouter::class => [
        'detect_duplicates' => false,  // Optional: disable for performance
    ],
];

```

### Testing Environment

```php
// config/autoload/testing.local.php
use Webware\Router\QueryParamRouter;
return [
    QueryParamRouter::class => [
        'detect_duplicates' => true,  // Enabled to catch test issues
    ],
];

```

## Advanced Configuration

### Custom Factory

Create a custom factory to add additional logic:

```php
namespace App\Router;
use Psr\Container\ContainerInterface;
use Webware\Router\QueryParamRouter;
use Webware\Router\QueryParamDuplicateRouteDetector;
class CustomRouterFactory
{
    public function __invoke(ContainerInterface $container): QueryParamRouter
    {
        $config = $container->get('config');
        $routerConfig = $config[QueryParamRouter::class] ?? [];
        // Custom logic
        $detectDuplicates = $routerConfig['detect_duplicates'] ?? true;
        $detector = $detectDuplicates
            ? $container->get(QueryParamDuplicateRouteDetector::class)
            : null;
        $router = new QueryParamRouter($detector);
        // Auto-load routes from config
        if (isset($routerConfig['routes'])) {
            foreach ($routerConfig['routes'] as $routeConfig) {
                $route = $this->createRouteFromConfig($routeConfig, $container);
                $router->addRoute($route);
            }
        }
        return $router;
    }
    private function createRouteFromConfig(array $config, ContainerInterface $container)
    {
        // Route creation logic
    }
}

```

Register your custom factory:

```php
return [
    'dependencies' => [
        'factories' => [
            QueryParamRouter::class => App\Router\CustomRouterFactory::class,
        ],
    ],
];

```

### Lazy Route Loading

Load routes from configuration lazily:

```php
use Webware\Router\QueryParamRouter;
return [
    QueryParamRouter::class => [
        'detect_duplicates' => true,
        'routes' => [
            [
                'path' => '/forum',
                'middleware' => 'App\\Middleware\\ForumMiddleware',
                'query_params' => ['board'],
                'methods' => ['GET'],
                'name' => 'forum.board',
            ],
            [
                'path' => '/forum',
                'middleware' => 'App\\Middleware\\TopicMiddleware',
                'query_params' => ['board', 'topic'],
                'methods' => ['GET'],
                'name' => 'forum.topic',
            ],
        ],
    ],
];

```

### Delegator Pattern

Use a delegator to enhance the router:

```php
namespace App\Router;
use Psr\Container\ContainerInterface;
use Webware\Router\QueryParamRouter;
class RouterDelegatorFactory
{
    public function __invoke(
        ContainerInterface $container,
        string $serviceName,
        callable $callback
    ): QueryParamRouter {
        $router = $callback();
        // Add logging
        $logger = $container->get('Logger');
        $router = new LoggingRouterDecorator($router, $logger);
        // Add caching
        $cache = $container->get('Cache');
        $router = new CachingRouterDecorator($router, $cache);
        return $router;
    }
}

```

Register the delegator:

```php
return [
    'dependencies' => [
        'delegators' => [
            QueryParamRouter::class => [
                App\Router\RouterDelegatorFactory::class,
            ],
        ],
    ],
];

```

## Configuration File Organization

### Recommended Structure

```text
config/
├── config.php                          # Main aggregator
├── autoload/
│   ├── global.php                      # Common config
│   ├── local.php                       # Local overrides (gitignored)
│   ├── dependencies.global.php         # Service definitions
│   ├── router.global.php               # Router config
│   ├── development.local.php           # Dev environment
│   ├── production.local.php            # Prod environment
│   └── testing.local.php               # Test environment
└── routes.php                          # Route definitions

```

### Example Configuration Files

#### config/config.php

```php
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
$aggregator = new ConfigAggregator([
    \Laminas\Diactoros\ConfigProvider::class,
    \Webware\Router\ConfigProvider::class,
    new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
], 'data/cache/config-cache.php');
return $aggregator->getMergedConfig();

```

#### config/autoload/router.global.php

```php
use Webware\Router\QueryParamRouter;
return [
    QueryParamRouter::class => [
        'detect_duplicates' => true,
    ],
];

```

#### config/autoload/development.local.php

```php
use Webware\Router\QueryParamRouter;
return [
    'debug' => true,
    QueryParamRouter::class => [
        'detect_duplicates' => true,
    ],
];

```

#### config/autoload/production.local.php

```php
use Webware\Router\QueryParamRouter;
return [
    'debug' => false,
    QueryParamRouter::class => [
        'detect_duplicates' => false,
    ],
];

```

## Configuration Caching

### Enable Config Cache

```php
// config/config.php
$cacheConfig = [
    'config_cache_path' => 'data/cache/config-cache.php',
];
$aggregator = new ConfigAggregator(
    $providers,
    $cacheConfig['config_cache_path']
);

```

### Clear Config Cache

```bash
rm data/cache/config-cache.php

```

Or programmatically:

```php
// bin/clear-config-cache.php
$cacheFile = 'data/cache/config-cache.php';
if (file_exists($cacheFile)) {
    unlink($cacheFile);
    echo "Config cache cleared.\n";
}

```

## Troubleshooting

### Container Cannot Find Router

**Problem**: `ServiceNotFoundException: Unable to resolve service "Webware\Router\QueryParamRouter"`
**Solutions**:

- Verify ConfigProvider is registered:

```php
// config/config.php
$aggregator = new ConfigAggregator([
    \Webware\Router\ConfigProvider::class,  // Add this
    // ...
]);

```

- Clear config cache:

```bash
rm data/cache/config-cache.php

```

- Verify autoloader is updated:

```bash
composer dump-autoload

```

### Configuration Not Applied

**Problem**: Router ignores configuration changes
**Solutions**:

- Verify config is injected into container:

```php
$container->setService('config', $config);

```

- Clear config cache

- Check configuration file is loaded:

```php
// Add to config/config.php for debugging
var_dump($aggregator->getMergedConfig());

```

### Duplicate Detection Not Working

**Problem**: Duplicate routes not detected
**Solutions**:

- Verify configuration:

```php
$config = $container->get('config');
var_dump($config[QueryParamRouter::class]['detect_duplicates']);

```

- Ensure detector is passed to router:

```php
// In factory
$detector = $container->get(QueryParamDuplicateRouteDetector::class);
$router = new QueryParamRouter($detector);  // Don't pass null

```

## See Also

- [ConfigProvider](ConfigProvider.md) - Provider class details
- [QueryParamRouter](QueryParamRouter.md) - Router usage
- [Examples](Examples.md) - Configuration examples
