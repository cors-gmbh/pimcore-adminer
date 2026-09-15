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
use Pimcore\Cache;

/**
 * Connects Adminer to the Pimcore database without a login form.
 *
 * Who may use Adminer at all is decided by DefaultController before Adminer runs; this plugin
 * hands Adminer the credentials of the Pimcore connection and accepts every login.
 */
final class PimcoreAdminerPlugin extends \Adminer\Plugin
{
    private const DATABASES_CACHE_KEY = 'pimcore_adminer_databases';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function name(): string
    {
        return '';
    }

    /**
     * Adminer prints its login form until a login was posted once. The form already carries the
     * CSRF token, so it only needs the database name and is submitted right away.
     */
    public function loginForm(): void
    {
        echo \Adminer\script(
            "document.addEventListener('DOMContentLoaded', () => {"
            . " const db = document.querySelector('input[name=\"auth[db]\"]');"
            . " if (db) { db.value = '" . \Adminer\js_escape($this->database()) . "'; db.form.submit(); }"
            . ' });',
        );
    }

    public function permanentLogin(bool $create = false): string
    {
        return '';
    }

    /**
     * @param string $login
     * @param string $password
     */
    public function login($login, $password): bool
    {
        return true;
    }

    /**
     * @return array{string, string, string}
     */
    public function credentials(): array
    {
        /** @psalm-suppress InternalMethod the parameters are only reachable through getParams(), as in Pimcore\Db */
        $params = $this->connection->getParams();

        $host = $params['host'] ?? '';
        if (isset($params['port'])) {
            $host .= ':' . $params['port'];
        }

        // server, username and password for connecting to database
        return [
            $host,
            $params['user'] ?? '',
            $params['password'] ?? '',
        ];
    }

    public function database(): string
    {
        return (string) $this->connection->getDatabase();
    }

    /**
     * @return list<string>
     */
    public function databases(bool $flush = true): array
    {
        $databases = Cache::load(self::DATABASES_CACHE_KEY);

        if (!\is_array($databases) || [] === $databases) {
            $databases = $this->connection->fetchFirstColumn('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA');

            Cache::save($databases, self::DATABASES_CACHE_KEY);
        }

        return array_values(array_filter($databases, \is_string(...)));
    }

    /**
     * The Konya design as the only, light stylesheet: Adminer then neither loads its dark theme nor
     * looks for adminer.css in its own directory.
     *
     * @return array<string, string>
     */
    public function css(): array
    {
        return [AdminerPaths::DESIGNS_URL . AdminerPaths::DESIGN . '/adminer.css' => 'light'];
    }

    /**
     * No client-side version check against adminer.org, the version is managed by Composer.
     */
    public function verifyVersion(): bool
    {
        return false;
    }

    /**
     * Adminer runs inside a Studio widget, so it is not offered as an installable web app.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return [];
    }

    /**
     * SSL options of the PDO connection in the format of the login-ssl plugin.
     *
     * @return array<string, string>
     */
    public static function sslOptions(Connection $connection): array
    {
        /** @psalm-suppress InternalMethod the parameters are only reachable through getParams(), as in Pimcore\Db */
        $params = $connection->getParams();
        $driverOptions = $params['driverOptions'] ?? [];

        $ssl = [];
        foreach ([
            'key' => \Pdo\Mysql::ATTR_SSL_KEY,
            'cert' => \Pdo\Mysql::ATTR_SSL_CERT,
            'ca' => \Pdo\Mysql::ATTR_SSL_CA,
        ] as $key => $option) {
            if (isset($driverOptions[$option]) && \is_string($driverOptions[$option])) {
                $ssl[$key] = $driverOptions[$option];
            }
        }

        return $ssl;
    }
}
