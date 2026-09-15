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

namespace CORS\Bundle\AdminerBundle\Tests\Unit;

use CORS\Bundle\AdminerBundle\lib\Pim\AdminerObjectFactory;
use CORS\Bundle\AdminerBundle\lib\Pim\AdminerPaths;
use CORS\Bundle\AdminerBundle\lib\Pim\AdminerPlugins;
use CORS\Bundle\AdminerBundle\lib\Pim\PimcoreAdminerPlugin;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class PimcoreAdminerPluginTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // declared by Adminer's bootstrap at runtime
        require_once AdminerPaths::home() . '/adminer/include/plugin.inc.php';
        AdminerObjectFactory::includeUpstreamPlugins();
    }

    public function testCredentialsComeFromThePimcoreConnection(): void
    {
        $plugin = new PimcoreAdminerPlugin($this->connection([
            'host' => 'db',
            'port' => 3306,
            'user' => 'pimcore',
            'password' => 'secret',
        ]));

        self::assertSame(['db:3306', 'pimcore', 'secret'], $plugin->credentials());
        self::assertSame('pimcore', $plugin->database());
        self::assertTrue($plugin->login('', ''));
    }

    public function testCredentialsWithoutPort(): void
    {
        $plugin = new PimcoreAdminerPlugin($this->connection(['host' => 'localhost', 'user' => 'u', 'password' => 'p']));

        self::assertSame(['localhost', 'u', 'p'], $plugin->credentials());
    }

    public function testLoginFormIsSubmittedWithThePimcoreDatabase(): void
    {
        $plugin = new PimcoreAdminerPlugin($this->connection([]));

        self::assertInstanceOf(\Adminer\Plugin::class, $plugin);
        self::assertSame([], $plugin->manifest());
        self::assertFalse($plugin->verifyVersion());
        self::assertSame([AdminerPaths::DESIGNS_URL . 'konya/adminer.css' => 'light'], $plugin->css());
        self::assertFileExists(AdminerPaths::home() . '/designs/konya/adminer.css');
    }

    public function testPluginsAreRegisteredWithThePimcorePluginFirst(): void
    {
        $plugins = AdminerObjectFactory::createPlugins($this->connection([]));

        self::assertInstanceOf(PimcoreAdminerPlugin::class, $plugins[0]);
        self::assertInstanceOf(AdminerPlugins::class, $plugins[1]);
        self::assertContainsOnlyInstancesOf(\Adminer\Plugin::class, $plugins);
        self::assertNotContains(\AdminerLoginSsl::class, array_map('get_class', $plugins));
    }

    public function testSslOptionsOfThePdoConnectionEnableTheLoginSslPlugin(): void
    {
        $connection = $this->connection(['driverOptions' => [
            \Pdo\Mysql::ATTR_SSL_CA => '/etc/ssl/ca.pem',
            \Pdo\Mysql::ATTR_SSL_CERT => '/etc/ssl/client.pem',
        ]]);

        self::assertSame(['cert' => '/etc/ssl/client.pem', 'ca' => '/etc/ssl/ca.pem'], PimcoreAdminerPlugin::sslOptions($connection));
        self::assertContains(\AdminerLoginSsl::class, array_map('get_class', AdminerObjectFactory::createPlugins($connection)));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function connection(array $params): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getParams')->willReturn($params);
        $connection->method('getDatabase')->willReturn('pimcore');

        return $connection;
    }
}
