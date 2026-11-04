# Examples

Real-world usage examples for Query Parameter Router.

## Table of Contents

- [Legacy Application Routing](#legacy-application-routing)
- [API Routing](#api-routing)
- [Forum Application](#forum-application)
- [Multi-Tenant Application](#multi-tenant-application)
- [Action-Based Routing](#action-based-routing)
- [Complex Routing Scenarios](#complex-routing-scenarios)

## Legacy Application Routing

### SMF Forum URLs

Route legacy SMF-style URLs like `index.php?board=1&action=display`:

```php
use Webware\Router\QueryParamRoute;
use Webware\Router\QueryParamRouter;
$router = new QueryParamRouter(new QueryParamDuplicateRouteDetector());
// Board display: index.php?board=1
$router->addRoute(new QueryParamRoute(
    '/index.php',
    new DisplayBoardMiddleware(),
    ['board'],
    ['GET'],
    'forum.board'
));
// Topic display: index.php?board=1&topic=100
$router->addRoute(new QueryParamRoute(
    '/index.php',
    new DisplayTopicMiddleware(),
    ['board', 'topic'],
    ['GET'],
    'forum.topic'
));
// Post display: index.php?board=1&topic=100&msg=5
$router->addRoute(new QueryParamRoute(
    '/index.php',
    new DisplayPostMiddleware(),
    ['board', 'topic', 'msg'],
    ['GET'],
    'forum.post'
));
// Board actions: index.php?board=1&action=markasread
$router->addRoute(new QueryParamRoute(
    '/index.php',
    new BoardActionMiddleware(),
    ['board', 'action'],
    ['GET', 'POST'],
    'forum.board.action'
));

```

### Handling Requests

```php
// Request: GET /index.php?board=1&topic=100&page=2
$result = $router->match($request);
if ($result->isSuccess()) {
    $params = $result->getMatchedParams();
    // ['board' => '1', 'topic' => '100']
    // Note: 'page' is not included (not a required param)
    $route = $result->getMatchedRoute();
    $response = $route->process($request, $handler);
}

```

### Middleware Implementation

```php
namespace App\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
class DisplayTopicMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TopicRepository $topics,
        private TemplateRenderer $renderer
    ) {}
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $boardId = $request->getQueryParams()['board'] ?? null;
        $topicId = $request->getQueryParams()['topic'] ?? null;
        if (!$boardId || !$topicId) {
            return new Response\EmptyResponse(404);
        }
        $topic = $this->topics->findByBoardAndId($boardId, $topicId);
        return $this->renderer->render('forum/topic', [
            'topic' => $topic,
        ]);
    }
}

```

## API Routing

### REST-like API with Query Parameters

```php
// GET /api?resource=users&action=list
$router->addRoute(new QueryParamRoute(
    '/api',
    new ListResourceMiddleware(),
    ['resource', 'action'],
    ['GET'],
    'api.list'
));
// POST /api?resource=users&action=create
$router->addRoute(new QueryParamRoute(
    '/api',
    new CreateResourceMiddleware(),
    ['resource', 'action'],
    ['POST'],
    'api.create'
));
// PUT /api?resource=users&action=update&id=123
$router->addRoute(new QueryParamRoute(
    '/api',
    new UpdateResourceMiddleware(),
    ['resource', 'action', 'id'],
    ['PUT'],
    'api.update'
));
// DELETE /api?resource=users&action=delete&id=123
$router->addRoute(new QueryParamRoute(
    '/api',
    new DeleteResourceMiddleware(),
    ['resource', 'action', 'id'],
    ['DELETE'],
    'api.delete'
));

```

### Generic API Middleware

```php
class ApiMiddleware implements MiddlewareInterface
{
    public function __construct(private array $handlers) {}
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $params = $request->getQueryParams();
        $resource = $params['resource'] ?? null;
        $action = $params['action'] ?? null;
        $handlerKey = "$resource.$action";
        if (!isset($this->handlers[$handlerKey])) {
            return new JsonResponse(['error' => 'Action not found'], 404);
        }
        try {
            $result = $this->handlers[$handlerKey]->handle($request);
            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}

```

## Forum Application

### Complete Forum Routing

```php
use Webware\Router\QueryParamRoute;
use Webware\Router\QueryParamRouter;
use Webware\Router\QueryParamDuplicateRouteDetector;
class ForumRouterFactory
{
    public function create(): QueryParamRouter
    {
        $router = new QueryParamRouter(new QueryParamDuplicateRouteDetector());
        // Homepage
        $router->addRoute(new QueryParamRoute(
            '/',
            new ForumHomeMiddleware(),
            [],
            ['GET'],
            'home'
        ));
        // Board category list
        $router->addRoute(new QueryParamRoute(
            '/',
            new BoardCategoryMiddleware(),
            ['cat'],
            ['GET'],
            'category'
        ));
        // Board list/display
        $router->addRoute(new QueryParamRoute(
            '/',
            new BoardDisplayMiddleware(),
            ['board'],
            ['GET'],
            'board'
        ));
        // Topic display
        $router->addRoute(new QueryParamRoute(
            '/',
            new TopicDisplayMiddleware(),
            ['board', 'topic'],
            ['GET'],
            'topic'
        ));
        // Post actions
        $router->addRoute(new QueryParamRoute(
            '/',
            new PostActionMiddleware(),
            ['action'],
            ['GET', 'POST'],
            'post.action'
        ));
        // Moderation actions
        $router->addRoute(new QueryParamRoute(
            '/',
            new ModerationMiddleware(),
            ['action', 'topic'],
            ['POST'],
            'moderate.topic'
        ));
        $router->addRoute(new QueryParamRoute(
            '/',
            new ModerationMiddleware(),
            ['action', 'board'],
            ['POST'],
            'moderate.board'
        ));
        // User profile
        $router->addRoute(new QueryParamRoute(
            '/',
            new ProfileMiddleware(),
            ['action', 'u'],
            ['GET'],
            'profile'
        ));
        return $router;
    }
}

```

### With Middleware Pipeline

```php
use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Router\RouteResult;
$app = new MiddlewarePipe();
// Error handling
$app->pipe(new ErrorHandlerMiddleware());
// Session
$app->pipe(new SessionMiddleware());
// Authentication
$app->pipe(new AuthenticationMiddleware());
// Routing
$app->pipe(new class($router) implements MiddlewareInterface {
    public function __construct(private QueryParamRouter $router) {}
    public function process($request, $handler): ResponseInterface {
        $result = $this->router->match($request);
        return $handler->handle(
            $request->withAttribute(RouteResult::class, $result)
        );
    }
});
// Not found handler
$app->pipe(new class implements MiddlewareInterface {
    public function process($request, $handler): ResponseInterface {
        $result = $request->getAttribute(RouteResult::class);
        if ($result && $result->isFailure()) {
            return new Response\HtmlResponse('404 Not Found', 404);
        }
        return $handler->handle($request);
    }
});
// Dispatch
$app->pipe(new class implements MiddlewareInterface {
    public function process($request, $handler): ResponseInterface {
        $result = $request->getAttribute(RouteResult::class);
        if ($result && $result->isSuccess()) {
            return $result->getMatchedRoute()->process($request, $handler);
        }
        return $handler->handle($request);
    }
});

```

## Multi-Tenant Application

### Tenant-Based Routing

```php
// Tenant selection: ?tenant=acme
$router->addRoute(new QueryParamRoute(
    '/',
    new TenantMiddleware(),
    ['tenant'],
    null,
    'tenant.home'
));
// Tenant dashboard: ?tenant=acme&action=dashboard
$router->addRoute(new QueryParamRoute(
    '/',
    new TenantDashboardMiddleware(),
    ['tenant', 'action'],
    ['GET'],
    'tenant.dashboard'
));
// Tenant resources: ?tenant=acme&resource=users
$router->addRoute(new QueryParamRoute(
    '/manage',
    new TenantResourceMiddleware(),
    ['tenant', 'resource'],
    ['GET'],
    'tenant.resource'
));

```

### Tenant Middleware

```php
class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(private TenantRepository $tenants) {}
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $tenantId = $request->getQueryParams()['tenant'] ?? null;
        if (!$tenantId) {
            return new Response\RedirectResponse('/select-tenant');
        }
        $tenant = $this->tenants->find($tenantId);
        if (!$tenant) {
            return new Response\HtmlResponse('Tenant not found', 404);
        }
        // Add tenant to request
        $request = $request->withAttribute('tenant', $tenant);
        return $handler->handle($request);
    }
}

```

## Action-Based Routing

### CRUD Operations

```php
// Create
$router->addRoute(new QueryParamRoute(
    '/manage',
    new CreateEntityMiddleware(),
    ['entity', 'action'],
    ['GET', 'POST'],
    'manage.create'
));
// Read/List
$router->addRoute(new QueryParamRoute(
    '/manage',
    new ListEntityMiddleware(),
    ['entity'],
    ['GET'],
    'manage.list'
));
// Update
$router->addRoute(new QueryParamRoute(
    '/manage',
    new UpdateEntityMiddleware(),
    ['entity', 'action', 'id'],
    ['GET', 'POST'],
    'manage.update'
));
// Delete
$router->addRoute(new QueryParamRoute(
    '/manage',
    new DeleteEntityMiddleware(),
    ['entity', 'action', 'id'],
    ['POST'],
    'manage.delete'
));

```

### Action Dispatcher

```php
class ActionDispatcherMiddleware implements MiddlewareInterface
{
    private array $actions = [];
    public function registerAction(string $name, callable $handler): void
    {
        $this->actions[$name] = $handler;
    }
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $action = $request->getQueryParams()['action'] ?? null;
        if (!$action || !isset($this->actions[$action])) {
            return new Response\HtmlResponse('Action not found', 404);
        }
        return $this->actions[$action]($request, $handler);
    }
}
// Usage
$dispatcher = new ActionDispatcherMiddleware();
$dispatcher->registerAction('create', function($req, $handler) {
    return new Response\HtmlResponse('Create form');
});
$dispatcher->registerAction('edit', function($req, $handler) {
    return new Response\HtmlResponse('Edit form');
});
$dispatcher->registerAction('delete', function($req, $handler) {
    return new Response\RedirectResponse('/success');
});

```

## Complex Routing Scenarios

### Hierarchical Resources

```php
// Organization: ?org=1
$router->addRoute(new QueryParamRoute(
    '/admin',
    new OrgMiddleware(),
    ['org'],
    ['GET'],
    'org'
));
// Department in org: ?org=1&dept=10
$router->addRoute(new QueryParamRoute(
    '/admin',
    new DeptMiddleware(),
    ['org', 'dept'],
    ['GET'],
    'org.dept'
));
// Team in department: ?org=1&dept=10&team=5
$router->addRoute(new QueryParamRoute(
    '/admin',
    new TeamMiddleware(),
    ['org', 'dept', 'team'],
    ['GET'],
    'org.dept.team'
));
// User in team: ?org=1&dept=10&team=5&user=100
$router->addRoute(new QueryParamRoute(
    '/admin',
    new UserMiddleware(),
    ['org', 'dept', 'team', 'user'],
    ['GET'],
    'org.dept.team.user'
));

```

### Mixed Path and Query Parameters

```php
// Path-based resource with query param actions
$router->addRoute(new QueryParamRoute(
    '/users/{id}',
    new UserProfileMiddleware(),
    [],
    ['GET'],
    'user.profile'
));
$router->addRoute(new QueryParamRoute(
    '/users/{id}',
    new UserEditMiddleware(),
    ['action'],
    ['GET', 'POST'],
    'user.edit'
));
// URI generation
$uri = $router->generateUri('user.profile', ['id' => '42']);
// /users/42
$uri = $router->generateUri('user.edit', ['id' => '42'], [
    'query' => ['action' => 'edit']
]);
// /users/42?action=edit

```

### Conditional Route Registration

```php
class RouteConfigurator
{
    public function __construct(
        private QueryParamRouter $router,
        private array $features
    ) {}
    public function configure(): void
    {
        // Always available
        $this->router->addRoute(new QueryParamRoute(
            '/',
            new HomeMiddleware(),
            [],
            ['GET'],
            'home'
        ));
        // Feature-flagged routes
        if ($this->features['forum_enabled']) {
            $this->addForumRoutes();
        }
        if ($this->features['api_enabled']) {
            $this->addApiRoutes();
        }
        if ($this->features['admin_enabled']) {
            $this->addAdminRoutes();
        }
    }
    private function addForumRoutes(): void
    {
        $this->router->addRoute(new QueryParamRoute(
            '/forum',
            new ForumMiddleware(),
            ['board'],
            ['GET'],
            'forum'
        ));
    }
    // ... other route methods
}

```

## Testing Examples

### Integration Test

```php
use PHPUnit\Framework\TestCase;
use Laminas\Diactoros\ServerRequest;
class ForumRoutingTest extends TestCase
{
    private QueryParamRouter $router;
    protected function setUp(): void
    {
        $this->router = (new ForumRouterFactory())->create();
    }
    public function testBoardDisplay(): void
    {
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams(['board' => '1']);
        $result = $this->router->match($request);
        $this->assertTrue($result->isSuccess());
        $this->assertSame('board', $result->getMatchedRouteName());
        $this->assertSame(['board' => '1'], $result->getMatchedParams());
    }
    public function testTopicDisplayIsMoreSpecific(): void
    {
        $request = new ServerRequest([], [], '/', 'GET');
        $request = $request->withQueryParams([
            'board' => '1',
            'topic' => '100'
        ]);
        $result = $this->router->match($request);
        $this->assertTrue($result->isSuccess());
        $this->assertSame('topic', $result->getMatchedRouteName());
    }
}

```

## See Also

- [QueryParamRouter](QueryParamRouter.md) - Router API reference
- [QueryParamRoute](QueryParamRoute.md) - Route configuration
- [Configuration](Configuration.md) - Setup and configuration
