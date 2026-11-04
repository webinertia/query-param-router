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

use Psr\Container\ContainerInterface;

/**
 * Factory for creating QueryParamDuplicateRouteDetector instances.
 */
final class QueryParamDuplicateRouteDetectorFactory
{
    public function __invoke(ContainerInterface $_container): QueryParamDuplicateRouteDetector
    {
        return new QueryParamDuplicateRouteDetector();
    }
}
