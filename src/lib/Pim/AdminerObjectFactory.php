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

use Doctrine\DBAL\Connection;

/**
 * Builds the Adminer\Plugins instance returned by adminer_object().
 *
 * Adminer asks for it from inside its bootstrap, after it has declared Adminer\Plugin, so the
 * upstream plugin files can only be included at that point.
 */
final class AdminerObjectFactory
{
    /**
     * Upstream plugins in use, class name => file in vendor/vrana/adminer/plugins.
     */
    private const UPSTREAM_PLUGINS = [
        \AdminerFrames::class => 'frames.php',
        \AdminerTablesFilter::class => 'tables-filter.php',
        \AdminerDumpDate::class => 'dump-date.php',
        \AdminerDumpJson::class => 'dump-json.php',
        \AdminerDumpBz2::class => 'dump-bz2.php',
        \AdminerDumpZip::class => 'dump-zip.php',
        \AdminerDumpXml::class => 'dump-xml.php',
        \AdminerDumpAlter::class => 'dump-alter.php',
        \AdminerLoginSsl::class => 'login-ssl.php',
    ];

    private static ?Connection $connection = null;

    public static function configure(Connection $connection): void
    {
        self::$connection = $connection;
    }

    public static function create(): \Adminer\Plugins
    {
        $connection = self::$connection;
        if (null === $connection) {
            throw new \LogicException('AdminerObjectFactory::configure() has to be called before Adminer is included.');
        }

        self::includeUpstreamPlugins();

        return new \Adminer\Plugins(self::createPlugins($connection));
    }

    /**
     * The plugin files extend Adminer\Plugin and can only be loaded once Adminer declared it. Composer
     * lists them in its classmap, so they are included only if nothing autoloaded them already.
     */
    public static function includeUpstreamPlugins(): void
    {
        $pluginDir = AdminerPaths::home() . '/plugins';

        foreach (self::UPSTREAM_PLUGINS as $class => $file) {
            if (!class_exists($class, false)) {
                /** @psalm-suppress UnresolvableInclude */
                include_once $pluginDir . '/' . $file;
            }
        }
    }

    /**
     * @return list<object>
     */
    public static function createPlugins(Connection $connection): array
    {
        // the Pimcore plugin comes first: Adminer uses the first non-null return value of a hook
        $plugins = [
            new PimcoreAdminerPlugin($connection),
            new AdminerPlugins(),
            new \AdminerFrames(),
            new \AdminerTablesFilter(),
            new \AdminerDumpDate(),
            new \AdminerDumpJson(),
            new \AdminerDumpBz2(),
            new \AdminerDumpZip(),
            new \AdminerDumpXml(),
            new \AdminerDumpAlter(),
        ];

        $ssl = PimcoreAdminerPlugin::sslOptions($connection);
        if ([] !== $ssl) {
            $plugins[] = new \AdminerLoginSsl($ssl);
        }

        return $plugins;
    }
}
