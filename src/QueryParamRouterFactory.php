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

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function is_array;
use function is_bool;

/**
 * Factory for creating QueryParamRouter instances.
 *
 * Reads configuration to determine if duplicate detection should be enabled.
 * Configuration key: QueryParamRouter::class => ['detect_duplicates' => true]
 */
final class QueryParamRouterFactory
{
    /**
     * @return array<array-key, mixed>
     */
    private function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function asBool(mixed $value, bool $default): bool
    {
        return is_bool($value) ? $value : $default;
    }

    private function asDuplicateDetector(mixed $value): QueryParamDuplicateRouteDetector
    {
        return $value instanceof QueryParamDuplicateRouteDetector ? $value : new QueryParamDuplicateRouteDetector();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function getDuplicateDetector(ContainerInterface $container): QueryParamDuplicateRouteDetector
    {
        if (! $container->has(QueryParamDuplicateRouteDetector::class)) {
            return new QueryParamDuplicateRouteDetector();
        }

        return $this->asDuplicateDetector($container->get(QueryParamDuplicateRouteDetector::class));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return array<array-key, mixed>
     */
    private function getRouterConfig(ContainerInterface $container): array
    {
        if (! $container->has('config')) {
            return [];
        }

        $config = $this->asArray($container->get('config'));

        return $this->asArray($config[QueryParamRouter::class] ?? null);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): QueryParamRouter
    {
        $routerConfig = $this->getRouterConfig($container);

        $detector = $this->asBool($routerConfig['detect_duplicates'] ?? null, true)
            ? $this->getDuplicateDetector($container)
            : null;

        return new QueryParamRouter($detector);
    }
}
