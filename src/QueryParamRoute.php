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

use Mezzio\Router\Route;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;
use function array_keys;
use function implode;
use function is_array;
use function is_scalar;
use function is_string;

/**
 * Route that matches based on query parameters in addition to path and HTTP method.
 *
 * Wraps a Mezzio Route and adds matching on query parameter keys and values.
 * Routes match when all required query parameter keys are present in the request
 * (case-sensitive). Additional query parameters are allowed.
 *
 * Query parameters can be specified in two ways:
 * 1. List of keys only: ['action', 'u'] - matches when keys are present with any value
 * 2. Associative array with constraints: ['action' => 'profile', 'u' => null] - matches
 *    specific values, or any value when constraint is null
 *
 * Mezzio\Router\Route is final, so this class composes one rather than extending
 * it. Call toRoute() to obtain the wrapped Route, or pass this instance directly
 * to QueryParamRouter::addRoute().
 */
final class QueryParamRoute implements MiddlewareInterface
{
    public const ?string HTTP_METHOD_ANY = Route::HTTP_METHOD_ANY;

    public const string HTTP_METHOD_SEPARATOR = Route::HTTP_METHOD_SEPARATOR;

    private readonly Route $route;

    /**
     * Query parameter constraints (param name => value constraint)
     * Value of null means any value is acceptable
     *
     * @var array<string, mixed>
     */
    private array $queryParamConstraints = [];

    /**
     * @param non-empty-string $path
     * @param array<string, mixed>|list<string> $queryParamKeys Query parameter keys (backward compatible)
     *        or constraints (key => value pairs, null value = any value)
     * @param list<string>|null $methods
     */
    public function __construct(
        string $path,
        MiddlewareInterface $middleware,
        array $queryParamKeys = [],
        ?array $methods = self::HTTP_METHOD_ANY,
        ?string $name = null,
    ) {
        $this->queryParamConstraints = $this->normalizeQueryParams($queryParamKeys);

        if (null === $name || '' === $name) {
            $name = $this->generateName($path, $this->queryParamConstraints, $methods);
        }

        $this->route = new Route($path, $middleware, $methods, $name);

        $options                 = $this->route->getOptions();
        $options['query_params'] = $this->queryParamConstraints;
        $this->route->setOptions($options);
    }

    public function allowsAnyMethod(): bool
    {
        return $this->route->allowsAnyMethod();
    }

    public function allowsMethod(string $method): bool
    {
        return $this->route->allowsMethod($method);
    }

    /**
     * @return null|list<string> Returns HTTP_METHOD_ANY or array of allowed methods.
     */
    public function getAllowedMethods(): ?array
    {
        return $this->route->getAllowedMethods();
    }

    public function getMiddleware(): MiddlewareInterface
    {
        return $this->route->getMiddleware();
    }

    public function getName(): string
    {
        return $this->route->getName();
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getOptions(): array
    {
        return $this->route->getOptions();
    }

    public function getPath(): string
    {
        return $this->route->getPath();
    }

    /**
     * Get the query parameter constraints for this route.
     *
     * @return array<string, mixed> Map of param name => constraint value (null = any value)
     */
    public function getQueryParamConstraints(): array
    {
        return $this->queryParamConstraints;
    }

    /**
     * Get the required query parameter keys for this route.
     *
     * @return list<string>
     */
    public function getQueryParamKeys(): array
    {
        return array_keys($this->queryParamConstraints);
    }

    /**
     * Check if the given query parameters match this route's requirements.
     *
     * All required query parameter keys must be present (case-sensitive).
     * If a constraint value is specified (not null), it must match exactly.
     * Additional query parameters are allowed.
     *
     * @param array<string, mixed> $queryParams Query parameters from the request
     */
    public function matchesQueryParams(array $queryParams): bool
    {
        foreach (array_keys($this->queryParamConstraints) as $paramName) {
            if (! array_key_exists($paramName, $queryParams)) {
                return false;
            }

            if (! $this->constraintSatisfied(
                $this->queryParamConstraints[$paramName] ?? null,
                $queryParams[$paramName] ?? null,
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * Proxies to the middleware composed during instantiation.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->route->process($request, $handler);
    }

    /**
     * @param non-empty-string $name
     */
    public function setName(string $name): void
    {
        $this->route->setName($name);
    }

    /**
     * Replace the wrapped route options.
     *
     * The query_params option is always re-applied so the constraints this
     * instance was built with cannot be dropped by a caller.
     *
     * @param array<array-key, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $options['query_params'] = $this->queryParamConstraints;

        $this->route->setOptions($options);
    }

    /**
     * Return the wrapped Mezzio route.
     */
    public function toRoute(): Route
    {
        return $this->route;
    }

    private function asParamName(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function constraintSatisfied(mixed $constraint, mixed $value): bool
    {
        return null === $constraint || $value === $constraint;
    }

    /**
     * Render a single constraint for route name generation.
     *
     * A null constraint renders as the bare key; scalar constraints render as
     * key=value; anything else is omitted.
     */
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
     * @param array<string, mixed> $queryParamConstraints
     * @return list<string>
     */
    private function describeConstraints(array $queryParamConstraints): array
    {
        $parts = [];

        foreach (array_keys($queryParamConstraints) as $key) {
            $part = $this->describeConstraint($key, $queryParamConstraints[$key] ?? null);

            if (null === $part) {
                continue;
            }

            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * Generate route name based on path, query params, and methods.
     *
     * Format:
     * - /path (no query params, any method)
     * - /path?key1&key2 (query params with any values, any method)
     * - /path?action=profile&u (specific value for action, any for u)
     * - /path^GET:POST (no query params, specific methods)
     * - /path?key1&key2^GET:POST (query params and specific methods)
     *
     * @param array<string, mixed> $queryParamConstraints
     * @param list<string>|null $methods
     */
    private function generateName(string $path, array $queryParamConstraints, ?array $methods): string
    {
        $name = $path;

        if ([] !== $queryParamConstraints) {
            $name .= '?' . implode('&', $this->describeConstraints($queryParamConstraints));
        }

        if (is_array($methods)) {
            $name .= '^' . implode(self::HTTP_METHOD_SEPARATOR, $methods);
        }

        return $name;
    }

    /**
     * Normalize query params input to constraints format.
     *
     * Supports backward compatibility:
     * - Indexed array ['action', 'u'] => ['action' => null, 'u' => null]
     * - Associative array ['action' => 'profile', 'u' => null] => unchanged
     *
     * @param array<array-key, mixed> $queryParams
     * @return array<string, mixed>
     */
    private function normalizeQueryParams(array $queryParams): array
    {
        $normalized = [];

        foreach (array_keys($queryParams) as $key) {
            if (is_string($key)) {
                $normalized[$key] = $queryParams[$key] ?? null;
                continue;
            }

            $listKey = $this->asParamName($queryParams[$key] ?? null);

            if (null === $listKey) {
                continue;
            }

            $normalized[$listKey] = null;
        }

        return $normalized;
    }
}
