# ConfigProvider

`Webware\Router\ConfigProvider` provides service manager configuration for integrating the Query Parameter Router with Laminas and Mezzio applications.

## Class Overview

```php
namespace Webware\Router;
final class ConfigProvider
{
    public function __invoke(): array;
    public function getDependencies(): array;
    public function getRouterConfig(): array;
}

```

## Usage

### With Laminas Config Aggregator

The recommended way to use the ConfigProvider is with `laminas-config-aggregator`:

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;
use Webware\Router\ConfigProvider;
$aggregator = new ConfigAggregator([
    // Other config providers
    Mezzio\Router\ConfigProvider::class,
    // Query Parameter Router
    ConfigProvider::class,
    // Application config
    new ArrayProvider($config),
], $cacheConfig['config_cache_path'] ?? null);
return $aggregator->getMergedConfig();

```

### Manual Configuration

You can also use the ConfigProvider methods directly:

```php
// config/autoload/dependencies.global.php
use Webware\Router\ConfigProvider;
$provider = new ConfigProvider();
return $provider->getDependencies();

```

## Configuration Structure

The ConfigProvider returns the following structure when invoked:

```php
[
    'dependencies' => [
        'factories' => [
            QueryParamRouter::class => QueryParamRouterFactory::class,
            QueryParamDuplicateRouteDetector::class => QueryParamDuplicateRouteDetectorFactory::class,
        ],
        'aliases' => [],
    ],
    'Webware\Router\QueryParamRouter' => [
        'detect_duplicates' => true,
    ],
]

```

## Methods

### `__invoke(): array`

Returns the complete configuration array.

```php
$provider = new ConfigProvider();
$config = $provider();
// $config['dependencies']['factories'][...]
// $config['Webware\Router\QueryParamRouter'][...]

```

### `getDependencies(): array`

Returns only the dependencies configuration.

```php
$provider = new ConfigProvider();
$dependencies = $provider->getDependencies();
// [
//     'factories' => [...],
//     'aliases' => [...],
// ]

```

### `getRouterConfig(): array`

Returns the default router configuration.

```php
$provider = new ConfigProvider();
$routerConfig = $provider->getRouterConfig();
// ['detect_duplicates' => true]

```

## Service Registration

### Factories

The ConfigProvider registers the following factories:

#### QueryParamRouterFactory

Creates `QueryParamRouter` instances with optional duplicate detection.

```php
use Webware\Router\QueryParamRouter;
// Retrieve from container
$router = $container->get(QueryParamRouter::class);

```

#### QueryParamDuplicateRouteDetectorFactory

Creates `QueryParamDuplicateRouteDetector` instances.

```php
use Webware\Router\QueryParamDuplicateRouteDetector;
// Retrieve from container
$detector = $container->get(QueryParamDuplicateRouteDetector::class);

```

### Aliases

By default, no aliases are registered. You can register an alias to make `QueryParamRouter` the default `RouterInterface` implementation:

```php
// config/autoload/dependencies.global.php
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

## Configuration Options

### Router Configuration Key

Configuration is stored under the `QueryParamRouter::class` key:

```php
use Webware\Router\QueryParamRouter;
return [
    QueryParamRouter::class => [
        'detect_duplicates' => true,  // Enable/disable duplicate detection
    ],
];

```

### Duplicate Detection

**Default**: `true`
Enable or disable duplicate route detection:

```php
// config/autoload/router.global.php
use Webware\Router\QueryParamRouter;
return [
    QueryParamRouter::class => [
        'detect_duplicates' => true,  // Enabled (recommended for development)
    ],
];
// Or disable in production (optional)
// config/autoload/router.local.php
return [
    QueryParamRouter::class => [
        'detect_duplicates' => false,
    ],
];

```

## Complete Integration Example

### 1. Install Dependencies

```bash
composer require webware/query-param-router
composer require laminas/laminas-config-aggregator
composer require laminas/laminas-servicemanager

```

`webware/query-param-router` is currently a pre-release, so the consuming project's root must allow pre-release stability. The Webware application skeleton declares `minimum-stability: dev` with `prefer-stable: true`, which satisfies that, so no stability change is needed. Every runtime dependency of the router is stable, so the router is the only pre-release in the graph.

### 2. Configure Config Aggregator

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Webware\Router\ConfigProvider as QueryRouterConfigProvider;
$aggregator = new ConfigAggregator([
    // Mezzio/Laminas providers
    \Laminas\Diactoros\ConfigProvider::class,
    // Query Parameter Router
    QueryRouterConfigProvider::class,
    // Load application config
    new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
], $cacheConfig['config_cache_path'] ?? null);
return $aggregator->getMergedConfig();

```

### 3. Create Service Manager

```php
// public/index.php
use Laminas\ServiceManager\ServiceManager;
$config = require __DIR__ . '/../config/config.php';
$container = new ServiceManager($config['dependencies']);
$container->setService('config', $config);

```

### 4. Use the Router

```php
use Webware\Router\QueryParamRouter;
$router = $container->get(QueryParamRouter::class);

```

## Environment-Specific Configuration

### Development

```php
// config/autoload/development.local.php
use Webware\Router\QueryParamRouter;
return [
    QueryParamRouter::class => [
        'detect_duplicates' => true,  // Enable for early error detection
    ],
];

```

### Production

```php
// config/autoload/production.local.php
use Webware\Router\QueryParamRouter;
return [
    QueryParamRouter::class => [
        'detect_duplicates' => false,  // Optional: disable for minimal perf gain
    ],
];

```

### Testing

```php
// config/autoload/testing.local.php
use Webware\Router\QueryParamRouter;
return [
    QueryParamRouter::class => [
        'detect_duplicates' => true,  // Enable to catch config issues
    ],
];

```

## Using with Mezzio

### Application Factory

```php
// src/AppFactory.php
use Laminas\ServiceManager\ServiceManager;
use Webware\Router\ConfigProvider;
use Webware\Router\QueryParamRouter;
class AppFactory
{
    public static function create(): Application
    {
        $config = require __DIR__ . '/../config/config.php';
        $container = new ServiceManager($config['dependencies']);
        $container->setService('config', $config);
        // Get router from container
        $router = $container->get(QueryParamRouter::class);
        return new Application($router, $container);
    }
}

```

### Route Configuration

```php
// config/routes.php
use Webware\Router\QueryParamRoute;
return function ($app, $factory, $container) {
    // Add routes to the application
    $app->route('/forum', QueryParamRoute::class, ['action'])
        ->setName('forum');
    $app->route('/api', QueryParamRoute::class, ['resource', 'action'])
        ->setMethods(['POST', 'PUT'])
        ->setName('api');
};

```

## Custom Configuration

### Extending the ConfigProvider

```php
namespace App;
use Webware\Router\ConfigProvider as BaseConfigProvider;
class CustomRouterConfigProvider extends BaseConfigProvider
{
    public function __invoke(): array
    {
        $config = parent::__invoke();
        // Add custom configuration
        $config['dependencies']['factories']['App\\CustomRouterService'] =
            'App\\CustomRouterServiceFactory';
        return $config;
    }
}

```

### Adding Custom Services

```php
// config/autoload/dependencies.global.php
use Webware\Router\QueryParamRouter;
return [
    'dependencies' => [
        'factories' => [
            'App\\RouteLoader' => function ($container) {
                return new RouteLoader(
                    $container->get(QueryParamRouter::class)
                );
            },
        ],
    ],
];

```

## Troubleshooting

### Router Not Found in Container

**Problem**: `ServiceNotFoundException` when requesting `QueryParamRouter::class`
**Solution**: Ensure ConfigProvider is registered in config aggregator:

```php
// config/config.php
$aggregator = new ConfigAggregator([
    \Webware\Router\ConfigProvider::class,  // Must be present
    // ...
]);

```

### Configuration Not Applied

**Problem**: Router still uses default configuration
**Solution**: Ensure config is passed to ServiceManager and configuration file is loaded:

```php
// Verify config is loaded
$container = new ServiceManager($config['dependencies']);
$container->setService('config', $config);  // Important!

```

### Factory Not Found

**Problem**: `ServiceNotFoundException` for factory class
**Solution**: Ensure composer autoload is up to date:

```bash
composer dump-autoload

```

## See Also

- [Configuration Guide](Configuration.md) - Complete configuration reference
- [QueryParamRouter](QueryParamRouter.md) - Router usage
- [Examples](Examples.md) - Integration examples
