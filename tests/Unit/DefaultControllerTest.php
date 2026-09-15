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

use CORS\Bundle\AdminerBundle\Controller\DefaultController;
use CORS\Bundle\AdminerBundle\lib\Pim\AdminerPaths;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Pimcore\Model\User;
use Pimcore\Security\User\User as SecurityUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class DefaultControllerTest extends TestCase
{
    public function testNonAdminUsersAreDenied(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->controller(false)->proxyAction($this->assetRequest('static', 'default.css'));
    }

    public function testAdminerAssetsAreServedToAdmins(): void
    {
        $response = $this->controller(true)->proxyAction($this->assetRequest('static', 'default.css'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/css', $response->headers->get('Content-Type'));
        self::assertNotEmpty($response->getContent());
    }

    public function testDesignIsServed(): void
    {
        $response = $this->controller(true)->proxyAction($this->assetRequest('designs', 'konya/adminer.css'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('#menu', (string) $response->getContent());
    }

    public function testAssetsOutsideTheAdminerDirectoryAreNotServed(): void
    {
        self::assertNull(DefaultController::resolveAssetPath(AdminerPaths::home() . '/adminer/static', '../../composer.json'));
        self::assertNull(DefaultController::resolveAssetPath(AdminerPaths::home() . '/adminer/static', '../include/functions.inc.php'));

        $this->expectException(NotFoundHttpException::class);
        $this->controller(true)->proxyAction($this->assetRequest('static', '../index.php'));
    }

    public function testRelativeStaticUrlsPointToTheAssetRoute(): void
    {
        $html = "<link rel=\"stylesheet\" href=\"./static/default.css\">\n<script src='./static/functions.js' nonce=\"x\"></script>";

        self::assertSame(
            "<link rel=\"stylesheet\" href=\"/pimcore-studio/api/cors-adminer/adminer/static/default.css\">\n"
            . "<script src='/pimcore-studio/api/cors-adminer/adminer/static/functions.js' nonce=\"x\"></script>",
            DefaultController::rewriteStaticUrls($html),
        );
    }

    private function controller(bool $admin): DefaultController
    {
        $user = new User();
        $user->setAdmin($admin);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new SecurityUser($user), 'pimcore_studio'));

        return new DefaultController($this->createMock(Connection::class), $tokenStorage);
    }

    private function assetRequest(string $type, string $path): Request
    {
        $request = new Request();
        $request->attributes->set('type', $type);
        $request->attributes->set('path', $path);

        return $request;
    }
}
