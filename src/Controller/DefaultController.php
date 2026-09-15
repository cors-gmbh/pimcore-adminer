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

namespace CORS\Bundle\AdminerBundle\Controller;

use CORS\Bundle\AdminerBundle\lib\Pim\AdminerObjectFactory;
use CORS\Bundle\AdminerBundle\lib\Pim\AdminerPaths;
use Doctrine\DBAL\Connection;
use Pimcore\Model\User;
use Pimcore\Security\User\User as SecurityUser;
use Pimcore\Tool\Authentication;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DefaultController
{
    /**
     * Adminer lives under the Pimcore Studio API prefix so that it is covered by the
     * `pimcore_studio` firewall and the `^/pimcore-studio/api` access control every
     * Studio installation already has — Pimcore 2026 has no firewall on `/admin` any
     * more. The prefix is the Studio default; installations that change
     * `pimcore_studio_backend.url_prefix` fall back to the admin check in this
     * controller, which never depends on the firewall.
     */
    public const string ROUTE_PREFIX = AdminerPaths::ROUTE_PREFIX;

    private const array ASSET_CONTENT_TYPES = [
        'css' => 'text/css',
        'js' => 'text/javascript',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    #[Route(path: AdminerPaths::ADMINER_URL, name: 'cors_adminer')]
    public function adminerAction(Request $request, ?Profiler $profiler): Response
    {
        $this->denyUnlessAdmin($request);

        $profiler?->disable();

        $this->ensureSessionIsOpen($request);

        // Adminer's stop_session() turns session.use_cookies off for the rest of the
        // request. Symfony guards its own session start with
        // `ini_get('session.use_cookies') && headers_sent()`, so leaving it off disables
        // that guard for every listener running after this controller.
        $useCookies = ini_get('session.use_cookies');

        $adminerDir = AdminerPaths::home() . '/adminer';

        AdminerObjectFactory::configure($this->connection);
        require_once __DIR__ . '/../lib/Pim/adminer_object.php';

        // Adminer (development version) includes its files relative to the working directory
        chdir($adminerDir);
        ob_start(static fn (string $html): string => self::rewriteStaticUrls($html));

        /** @psalm-suppress UnresolvableInclude */
        include $adminerDir . '/index.php';

        @ob_end_flush();

        if (false !== $useCookies) {
            @ini_set('session.use_cookies', $useCookies);
        }

        // Persist whatever Adminer wrote to the session. Adminer calls exit() on its
        // login page and redirects, in which case PHP's shutdown handler does this instead.
        if (\PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }

        return $this->mergeAdminerHeaders(new Response());
    }

    #[Route(path: AdminerPaths::ADMINER_URL . '/static/{path}', name: 'cors_adminer_static', requirements: ['path' => '.+'], defaults: ['type' => 'static'])]
    #[Route(path: AdminerPaths::ADMINER_URL . '/designs/{path}', name: 'cors_adminer_designs', requirements: ['path' => '.+'], defaults: ['type' => 'designs'])]
    public function proxyAction(Request $request): Response
    {
        $this->denyUnlessAdmin($request);

        $path = $request->attributes->getString('path');
        $baseDir = AdminerPaths::home() . ('designs' === $request->attributes->getString('type') ? '/designs' : '/adminer/static');

        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        $filePath = isset(self::ASSET_CONTENT_TYPES[$extension]) ? self::resolveAssetPath($baseDir, $path) : null;

        if (null === $filePath) {
            throw new NotFoundHttpException('Adminer asset not found.');
        }

        $response = new Response((string) file_get_contents($filePath));
        $response->headers->set('Content-Type', self::ASSET_CONTENT_TYPES[$extension]);

        return $response;
    }

    /**
     * Adminer's development version references its assets relative to its own directory
     * (`./static/default.css`), which in the browser would resolve next to the route instead of
     * to the asset proxy.
     */
    public static function rewriteStaticUrls(string $html): string
    {
        return str_replace(
            ["'./static/", '"./static/'],
            ["'" . AdminerPaths::STATIC_URL, '"' . AdminerPaths::STATIC_URL],
            $html,
        );
    }

    /**
     * Confines a requested asset to the given Adminer directory. The path segment comes from the
     * URL, so without this a request for `..%2f..%2f<something>.css` would read any css/js file on
     * the filesystem.
     */
    public static function resolveAssetPath(string $baseDir, string $path): ?string
    {
        $realBase = realpath($baseDir);
        $realPath = realpath($baseDir . '/' . $path);

        if (false === $realBase || false === $realPath || !is_file($realPath)) {
            return null;
        }

        if (!str_starts_with($realPath, $realBase . \DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }

    /**
     * Adminer runs with the credentials of the Pimcore database connection and its own
     * login() always succeeds, so the route itself has to establish who is calling.
     *
     * The Studio firewall in front of ROUTE_PREFIX only gets us an authenticated Pimcore
     * user (ROLE_PIMCORE_USER in a standard setup); full database access is for admins,
     * and the check may not depend on a project's access_control rules being present.
     *
     * The user comes from the security token, or from the `pimcore_admin` session context
     * the Studio login writes — which is what authenticates the iframe request Studio
     * makes for the widget.
     */
    protected function denyUnlessAdmin(Request $request): void
    {
        $user = $this->getPimcoreUser($request);

        if (!$user instanceof User || !$user->isAdmin()) {
            throw new AccessDeniedHttpException('Adminer is available to Pimcore admin users only.');
        }
    }

    protected function getPimcoreUser(Request $request): ?User
    {
        $securityUser = $this->tokenStorage?->getToken()?->getUser();

        if ($securityUser instanceof SecurityUser) {
            return $securityUser->getUser();
        }

        return Authentication::authenticateSession($request);
    }

    /**
     * Pimcore/Symfony starts a native PHP session to authenticate this request and closes
     * it again before this controller runs, which leaves session_status() at
     * PHP_SESSION_NONE. Adminer's bootstrap would then start a session of its own
     * (`adminer_sid`, cookie path of the route) next to Pimcore's.
     *
     * Re-opening Pimcore's session lets Adminer share it instead, as it did before.
     *
     * It has to happen through Symfony's session object rather than a plain
     * session_start(): Studio's SessionCloseSubscriber closed the session with
     * $session->save(), which leaves NativeSessionStorage marked as not started. A raw
     * start would leave that flag alone, and ContextListener writing the security token
     * back on kernel.response would then call NativeSessionStorage::start() a second
     * time - after Adminer already flushed its output, and with Adminer's
     * `session.use_cookies = 0` disabling the headers_sent() guard, so the start fails
     * with "Failed to start the session." and the response dies as a 502.
     */
    protected function ensureSessionIsOpen(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if ($session->isStarted() || \PHP_SESSION_ACTIVE === session_status() || headers_sent()) {
            return;
        }

        $session->start();
    }

    protected function mergeAdminerHeaders(Response $response): Response
    {
        if (headers_sent()) {
            return $response;
        }

        $grouped = [];

        foreach (headers_list() as $header) {
            $parts = explode(':', $header, 2);

            if (2 !== \count($parts)) {
                continue;
            }

            $headerKey = trim($parts[0]);
            $headerValue = trim($parts[1]);

            if ('' === $headerKey || '' === $headerValue) {
                continue;
            }

            $grouped[$headerKey][] = $headerValue;
        }

        /*
         * Headers have to be handed over as a list per name. Symfony's
         * ResponseHeaderBag::set() empties its entire cookie jar whenever it is called
         * for Set-Cookie with $replace = true, so setting them one at a time in a loop
         * kept only the last cookie and silently discarded every cookie Adminer had
         * set before it (adminer_permanent, adminer_key, ...).
         */
        foreach ($grouped as $headerKey => $headerValues) {
            $response->headers->set($headerKey, $headerValues);
        }

        header_remove();

        return $response;
    }
}
