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

namespace Webware\Router;

use Mezzio\Router\Exception\DuplicateRouteException;
use Mezzio\Router\Exception\RuntimeException;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function array_keys;
use function count;
use function http_build_query;
use function is_array;
use function is_scalar;
use function is_string;
use function sprintf;
use function str_replace;

/**
 * Router implementation that matches routes based on query parameters.
 *
 * Routes are matched against the request path and query parameter keys.
 * If a route specifies required query parameter keys, all must be present
 * in the request (case-sensitive). Query parameter values are included
 * in the matched params.
 */
final class QueryParamRouter implements RouterInterface
{
    /**
     * Routes indexed by name
     *
     * @var array<string, QueryParamRoute|Route>
     */
    private array $routes = [];

    /**
     * Routes indexed by path for faster lookup
     *
     * @var array<string, list<QueryParamRoute|Route>>
     */
    private array $routesByPath = [];

    public function __construct(
        private ?QueryParamDuplicateRouteDetector $duplicateDetector = null,
    ) {}

    /**
     * @throws DuplicateRouteException When the duplicate detector rejects the route.
     */
    #[Override]
    public function addRoute(Route|QueryParamRoute $route): void
    {
        if (null !== $this->duplicateDetector) {
            $this->duplicateDetector->detectDuplicate($route instanceof QueryParamRoute ? $route->toRoute() : $route);
        }

        $this->routes[$route->getName()] = $route;

        $this->routesByPath[$route->getPath()][] = $route;
    }

    /**
     * @param array<array-key, mixed> $substitutions
     * @param array<array-key, mixed> $options
     */
    #[Override]
    public function generateUri(string $name, array $substitutions = [], array $options = []): string
    {
        if (! array_key_exists($name, $this->routes)) {
            throw new RuntimeException(sprintf('Cannot generate URI for route "%s"; route not found', $name));
        }

        $route = $this->routes[$name];
        $path  = $route->getPath();

        foreach (array_keys($substitutions) as $key) {
            $path = $this->replaceSubstitution($path, $key, $substitutions[$key] ?? null);
        }

        $query = [];

        if (is_array($options['query'] ?? null)) {
            $query = $options['query'];
        }

        if ([] !== $query) {
            $path .= '?' . http_build_query($query);
        }

        return $path;
    }

    #[Override]
    public function match(ServerRequestInterface $request): RouteResult
    {
        $path   = $request->getUri()->getPath();
        $method = $request->getMethod();

        /** @var array<string, mixed> $queryParams */
        $queryParams = $request->getQueryParams();

        $candidateRoutes = $this->routesByPath[$path] ?? [];
        $bestMatch       = null;

        foreach ($candidateRoutes as $route) {
            if (! $this->routeMatchesQueryParams($route, $queryParams)) {
                continue;
            }

            if (! $route->allowsMethod($method)) {
                continue;
            }

            if (null === $bestMatch || $this->scoreRoute($route) > $this->scoreRoute($bestMatch)) {
                $bestMatch = $route;
            }
        }

        if (null === $bestMatch) {
            return RouteResult::fromRouteFailure(null);
        }

        return RouteResult::fromRoute(
            $bestMatch instanceof QueryParamRoute ? $bestMatch->toRoute() : $bestMatch,
            $this->extractMatchedQueryParams($bestMatch, $queryParams),
        );
    }

    private function asParamName(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * Extract matched query parameter values.
     *
     * Only includes values for the route's required query param keys.
     *
     * @param array<string, mixed> $queryParams
     * @return array<string, mixed>
     */
    private function extractMatchedQueryParams(Route|QueryParamRoute $route, array $queryParams): array
    {
        $matched = [];

        foreach (array_keys($this->getRouteQueryParamConstraints($route)) as $key) {
            if (! array_key_exists($key, $queryParams)) {
                continue;
            }

            $matched[$key] = $queryParams[$key] ?? null;
        }

        return $matched;
    }

    /**
     * Read the route's query param constraints in either supported format.
     *
     * Accepts a list of keys (['action', 'u']) or a map of constraints
     * (['action' => 'profile', 'u' => null]) and normalizes both to a map of
     * param name => constraint value, where null means any value matches.
     *
     * @return array<string, mixed>
     */
    private function getRouteQueryParamConstraints(Route|QueryParamRoute $route): array
    {
        $options     = $route->getOptions();
        $queryParams = [];

        if (is_array($options['query_params'] ?? null)) {
            $queryParams = $options['query_params'];
        }

        $constraints = [];

        foreach (array_keys($queryParams) as $key) {
            if (is_string($key)) {
                $constraints[$key] = $queryParams[$key] ?? null;
                continue;
            }

            $listKey = $this->asParamName($queryParams[$key] ?? null);

            if (null === $listKey) {
                continue;
            }

            $constraints[$listKey] = null;
        }

        return $constraints;
    }

    /**
     * Replace a single `{name}` path segment with its substitution value.
     */
    private function replaceSubstitution(string $path, int|string $key, mixed $value): string
    {
        if (! is_scalar($value)) {
            return $path;
        }

        return str_replace("{{$key}}", (string) $value, $path);
    }

    /**
     * Check if route's query param requirements match the request's query params.
     *
     * @param array<string, mixed> $queryParams
     */
    private function routeMatchesQueryParams(Route|QueryParamRoute $route, array $queryParams): bool
    {
        $constraints = $this->getRouteQueryParamConstraints($route);

        foreach (array_keys($constraints) as $key) {
            if (! array_key_exists($key, $queryParams)) {
                return false;
            }

            if (null !== ($constraints[$key] ?? null) && $queryParams[$key] !== $constraints[$key]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Score a route so the most specific match wins.
     *
     * The score is the number of query parameter keys, plus a large bonus for
     * each parameter carrying a specific value constraint.
     */
    private function scoreRoute(Route|QueryParamRoute $route): int
    {
        $constraints = $this->getRouteQueryParamConstraints($route);
        $score       = count($constraints);

        foreach (array_keys($constraints) as $key) {
            if (null === ($constraints[$key] ?? null)) {
                continue;
            }

            $score += 100;
        }

        return $score;
    }
}
