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

/**
 * ConfigProvider for query parameter router.
 *
 * Provides dependency injection configuration for use with laminas-config-aggregator.
 *
 * Example usage in config/config.php:
 * <code>
 * $aggregator = new ConfigAggregator([
 *     \Webware\Router\ConfigProvider::class,
 *     // ... other config providers
 * ]);
 * </code>
 */
final class ConfigProvider
{
    /**
     * Returns dependency configuration.
     *
     * @return array<string, array<string, string>>
     */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                QueryParamRouter::class                 => QueryParamRouterFactory::class,
                QueryParamDuplicateRouteDetector::class => QueryParamDuplicateRouteDetectorFactory::class,
            ],
            'aliases'   => [
                // Optionally alias RouterInterface to QueryParamRouter
                // Uncomment if you want this router to be the default
                // RouterInterface::class => QueryParamRouter::class,
            ],
        ];
    }

    /**
     * Returns default router configuration.
     *
     * @return array<string, mixed>
     */
    public function getRouterConfig(): array
    {
        return [
            'detect_duplicates' => true,
        ];
    }

    /**
     * Returns configuration array.
     *
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies'          => $this->getDependencies(),
            QueryParamRouter::class => $this->getRouterConfig(),
        ];
    }
}
