<?php

declare(strict_types=1);

/*
 * CORS GmbH
 *
 * This source file is available under the MIT license
 *
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) CORS GmbH (https://www.cors.gmbh)
 * @license    https://www.cors.gmbh/license MIT
 *
 */

namespace CORS\Bundle\AdminerBundle\lib\Pim;

use Composer\InstalledVersions;

/**
 * Locations inside the installed `vrana/adminer` package and the URLs the bundle serves them under.
 */
final class AdminerPaths
{
    /**
     * Adminer lives under the Pimcore Studio API prefix so that it is covered by the
     * `pimcore_studio` firewall and the `^/pimcore-studio/api` access control every
     * Studio installation already has.
     */
    public const string ROUTE_PREFIX = '/pimcore-studio/api/cors-adminer';

    public const string ADMINER_URL = self::ROUTE_PREFIX . '/adminer';

    public const string STATIC_URL = self::ADMINER_URL . '/static/';

    public const string DESIGNS_URL = self::ADMINER_URL . '/designs/';

    public const string DESIGN = 'konya';

    public static function home(): string
    {
        $path = InstalledVersions::getInstallPath('vrana/adminer');

        if (null === $path || false === ($realPath = realpath($path))) {
            throw new \RuntimeException('The package vrana/adminer is not installed.');
        }

        return $realPath;
    }
}
