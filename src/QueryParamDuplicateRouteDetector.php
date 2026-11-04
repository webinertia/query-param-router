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
use Mezzio\Router\Route;

use function array_key_exists;
use function array_keys;
use function implode;
use function is_array;
use function is_scalar;
use function is_string;
use function sort;
use function sprintf;

use const SORT_STRING;

/**
 * Detects duplicate routes based on path, query parameter keys, and HTTP methods.
 *
 * A route is considered duplicate if it has:
 * - The same route name, OR
 * - The same path + query param keys + HTTP method combination
 *
 * Query param keys are compared case-sensitively but order-independently.
 */
final class QueryParamDuplicateRouteDetector
{
    private const string ROUTE_SEARCH_ANY = 'any';

    private const string ROUTE_SEARCH_METHODS = 'methods';

    /**
     * List of all routes indexed by name
     *
     * @var array<string, Route>
     */
    private array $routeNames = [];

    /**
     * Search structure for duplicate path-query-method detection
     * Indexed by: path -> query keys (sorted) -> method
     *
     * Example:
     * [
     *     '/api' => [
     *         'action,type' => [
     *             'any' => $route1,
     *         ],
     *         'action' => [
     *             'methods' => [
     *                 'GET' => $route2,
     *                 'POST' => $route3,
     *             ],
     *         ],
     *     ],
     * ]
     *
     * @var array<string, array<string, array{methods?: array<string, Route>, any?: Route}>>
     */
    private array $routePaths = [];

    /**
     * Determine if the route is duplicated in the current list.
     *
     * Checks if a route with the same name or path+query+method exists already;
     * if so, raises a DuplicateRouteException.
     *
     * @throws DuplicateRouteException On duplicate route detection.
     */
    public function detectDuplicate(Route|QueryParamRoute $route): void
    {
        $route = $route instanceof QueryParamRoute ? $route->toRoute() : $route;

        $this->throwOnDuplicate($route);
        $this->remember($route);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function describeConstraint(string $key, mixed $value): ?string
    {
        if (null === $value) {
            return $key;
        }

        if (! is_scalar($value)) {
            return null;
        }

        return $key . '=' . (string) $value;
    }

    /**
     * Render query parameter constraints for error messages.
     *
     * @param array<array-key, mixed> $queryParams
     * @return list<string>
     */
    private function describeQueryParams(array $queryParams): array
    {
        $parts = [];

        foreach (array_keys($queryParams) as $key) {
            if (! is_string($key)) {
                continue;
            }

            $part = $this->describeConstraint($key, $queryParams[$key] ?? null);

            if (null === $part) {
                continue;
            }

            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @throws DuplicateRouteException Always.
     */
    private function duplicateRouteDetected(Route $duplicate): void
    {
        $allowedMethods = $duplicate->getAllowedMethods();

        if (null === $allowedMethods || [] === $allowedMethods) {
            $allowedMethods = ['(any)'];
        }

        $name  = $duplicate->getName();
        $parts = $this->describeQueryParams($this->getQueryParamsFromRoute($duplicate));

        throw new DuplicateRouteException(sprintf(
            'Duplicate route detected; path "%s"%s answering to methods [%s]%s',
            $duplicate->getPath(),
            [] === $parts ? '' : sprintf(' with query params [%s]', implode(',', $parts)),
            implode(',', $allowedMethods),
            sprintf(', with name "%s"', $name),
        ));
    }

    /**
     * Get a normalized key for query parameters (sorted, case-sensitive).
     *
     * This ensures that routes with the same query params in different orders
     * are detected as duplicates, but routes with different value constraints
     * are NOT detected as duplicates.
     *
     * Format: "key1=value1,key2" (where key2 has no value constraint)
     */
    private function getQueryKey(Route $route): string
    {
        $parts = $this->describeQueryParams($this->getQueryParamsFromRoute($route));

        sort($parts, SORT_STRING);

        return implode(',', $parts);
    }

    /**
     * Extract query parameter constraints from route options.
     *
     * @return array<array-key, mixed> Map of param name => constraint value
     */
    private function getQueryParamsFromRoute(Route $route): array
    {
        $options = $route->getOptions();

        return $this->asArray($options['query_params'] ?? null);
    }

    private function remember(Route $route): void
    {
        $this->routeNames[$route->getName()] = $route;

        $path     = $route->getPath();
        $queryKey = $this->getQueryKey($route);

        if ($route->allowsAnyMethod()) {
            $this->routePaths[$path][$queryKey][self::ROUTE_SEARCH_ANY] = $route;
        }

        foreach ($route->getAllowedMethods() ?? [] as $method) {
            $this->routePaths[$path][$queryKey][self::ROUTE_SEARCH_METHODS][$method] = $route;
        }
    }

    /**
     * @throws DuplicateRouteException On duplicate route detection.
     */
    private function throwOnDuplicate(Route $route): void
    {
        if (array_key_exists($route->getName(), $this->routeNames)) {
            $this->duplicateRouteDetected($route);
        }

        $path     = $route->getPath();
        $queryKey = $this->getQueryKey($route);
        $entry    = $this->routePaths[$path][$queryKey] ?? null;

        if (null === $entry) {
            return;
        }

        if (array_key_exists(self::ROUTE_SEARCH_ANY, $entry)) {
            $this->duplicateRouteDetected($route);
        }

        $hasSpecificMethods = array_key_exists(self::ROUTE_SEARCH_METHODS, $entry);

        if ($route->allowsAnyMethod()) {
            if ($hasSpecificMethods) {
                $this->duplicateRouteDetected($route);
            }

            return;
        }

        $registered = $entry[self::ROUTE_SEARCH_METHODS] ?? [];

        foreach ($route->getAllowedMethods() ?? [] as $method) {
            if (! array_key_exists($method, $registered)) {
                continue;
            }

            $this->duplicateRouteDetected($route);
        }
    }
}
