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

namespace CORS\Bundle\AdminerBundle\Controller {
    use CORS\Bundle\AdminerBundle\lib\Pim\Helper;
    use Pimcore\Helper\Mail as MailHelper;
    use Pimcore\Model\User;
    use Pimcore\Security\User\User as SecurityUser;
    use Pimcore\Tool\Authentication;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
    use Symfony\Component\HttpKernel\Profiler\Profiler;
    use Symfony\Component\Routing\Annotation\Route;
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
        public const ROUTE_PREFIX = '/pimcore-studio/api/cors-adminer';

        protected string $adminerHome = '';

        public function __construct(private readonly ?TokenStorageInterface $tokenStorage = null)
        {
        }

        #[Route(path: self::ROUTE_PREFIX . '/adminer', name: 'cors_adminer')]
        public function adminerAction(Request $request, ?Profiler $profiler): Response
        {
            $this->denyUnlessAdmin($request);
            $this->prepare();

            $profiler?->disable();

            chdir($this->adminerHome . 'adminer');
            ob_start(static function (string $html) {
                try {
                    if (method_exists(MailHelper::class, 'setAbsolutePaths')) {
                        /** @psalm-suppress InternalMethod, InternalClass */
                        $html = MailHelper::setAbsolutePaths($html, null, Helper::getHostUrl() . self::ROUTE_PREFIX . '/adminer');
                    } else {
                        throw new \Exception('Method setAbsolutePaths does not exist in MailHelper.');
                    }

                    return str_replace('static/editing.js', Helper::getHostUrl() . self::ROUTE_PREFIX . '/adminer/static/editing.js', $html);
                } catch (\Exception $e) {
                    throw new \Exception('Error in MailHelper::setAbsolutePaths: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
                }
            });

            /** @psalm-suppress UnresolvableInclude */
            include $this->adminerHome . 'adminer/index.php';

            @ob_get_flush();

            $response = new Response();

            return $this->mergeAdminerHeaders($response);
        }

        #[Route(path: self::ROUTE_PREFIX . '/adminer/static/{path}', requirements: ['path' => '.*'])]
        #[Route(path: self::ROUTE_PREFIX . '/externals/{path}', requirements: ['path' => '.*'], defaults: ['type' => 'external'])]
        public function proxyAction(Request $request): Response
        {
            $this->denyUnlessAdmin($request);
            $this->prepare();

            $response = new Response();
            $content = '';

            // proxy for resources
            $path = $request->get('path');

            if (preg_match('@\.(css|js|ico|png|jpg|gif)$@', $path)) {
                /** @psalm-suppress InternalMethod, InternalClass */
                if ('external' === $request->get('type')) {
                    $path = '../' . $path;
                }

                if (str_starts_with($path, 'static/')) {
                    $path = 'adminer/' . $path;
                }

                $filePath = $this->resolveAssetPath($this->adminerHome . '/' . $path);
                if (null === $filePath || !file_exists($filePath)) {
                    $filePath = $this->resolveAssetPath($this->adminerHome . 'adminer/static/' . $path);
                }
                // it seems that css files need the right content-type (Chrome)
                if (preg_match('@.css$@', $path)) {
                    $response->headers->set('Content-Type', 'text/css');
                } elseif (preg_match('@.js$@', $path)) {
                    $response->headers->set('Content-Type', 'text/javascript');
                }

                if (null !== $filePath && file_exists($filePath)) {
                    $content = file_get_contents($filePath);

                    if (preg_match('@default.css$@', $path)) {
                        // append custom styles, because in Adminer everything is hardcoded
                        $content .= file_get_contents($this->adminerHome . 'designs/konya/adminer.css');
                    }
                }
            }

            $response->setContent($content);

            return $this->mergeAdminerHeaders($response);
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
         * Confines a requested asset to the Adminer package. The path segment comes from the
         * URL, so without this a request for `..%2f..%2f<something>.css` would read any
         * css/js file on the filesystem.
         */
        protected function resolveAssetPath(string $filePath): ?string
        {
            $realPath = realpath($filePath);
            $adminerRoot = realpath($this->adminerHome);

            if (false === $realPath || false === $adminerRoot) {
                return null;
            }

            if (!str_starts_with($realPath, $adminerRoot . \DIRECTORY_SEPARATOR)) {
                return null;
            }

            return $realPath;
        }

        public function prepare(): void
        {
            /** @psalm-suppress UndefinedConstant */
            $this->adminerHome = PIMCORE_COMPOSER_PATH . '/vrana/adminer/';
        }

        protected function mergeAdminerHeaders(Response $response): Response
        {
            if (!headers_sent()) {
                $headersRaw = headers_list();

                foreach ($headersRaw as $header) {
                    $header = explode(':', $header, 2);
                    [$headerKey, $headerValue] = $header;

                    if ($headerKey && $headerValue) {
                        $response->headers->set($headerKey, $headerValue);
                    }
                }

                header_remove();
            }

            return $response;
        }
    }
}

namespace {
    use Pimcore\Cache;
    use Pimcore\Db;
    use Pimcore\Tool\Session;

    if (!function_exists('adminer_object')) {
        function adminer_object()
        {
            /** @psalm-suppress UndefinedConstant */
            $pluginDir = PIMCORE_COMPOSER_PATH . '/vrana/adminer/plugins';

            /** @psalm-suppress UnresolvableInclude */
            include_once $pluginDir . '/plugin.php';

            foreach (glob($pluginDir . '/*.php') as $filename) {
                /** @psalm-suppress UnresolvableInclude */
                include_once $filename;
            }

            $plugins = [
                new \CORS\Bundle\AdminerBundle\lib\Pim\AdminerPlugins(),
                new \AdminerFrames(),
                new \AdminerDumpDate(),
                new \AdminerDumpJson(),
                new \AdminerDumpBz2(),
                new \AdminerDumpZip(),
                new \AdminerDumpXml(),
                new \AdminerDumpAlter(),
            ];

            // support for SSL (at least for PDO)
            /** @psalm-suppress InternalMethod, InternalClass */
            $driverOptions = \Pimcore\Db::get()->getParams()['driverOptions'] ?? [];
            $ssl = [
                'key' => $driverOptions[\PDO::MYSQL_ATTR_SSL_KEY] ?? null,
                'cert' => $driverOptions[\PDO::MYSQL_ATTR_SSL_CERT] ?? null,
                'ca' => $driverOptions[\PDO::MYSQL_ATTR_SSL_CA] ?? null,
            ];
            if (null !== $ssl['key'] || null !== $ssl['cert'] || null !== $ssl['ca']) {
                $plugins[] = new \AdminerLoginSsl($ssl);
            }

            class AdminerPimcore extends \AdminerPlugin
            {
                public function name(): string
                {
                    return '';
                }

                public function loginForm(): void
                {
                    parent::loginForm();
                    echo '<script' . nonce() . ">document.querySelector('input[name=auth\\\\[db\\\\]]').value='" . $this->database() . "'; document.querySelector('form').submit()</script>";
                }

                public function permanentLogin($create = false): string
                {
                    if (method_exists(Session::class, 'getSessionId')) {
                        return Session::getSessionId();
                    }

                    return '';
                }

                public function login($login, $password): bool
                {
                    return true;
                }

                public function credentials(): array
                {
                    /** @psalm-suppress InternalMethod, InternalClass */
                    $params = \Pimcore\Db::get()->getParams();

                    $host = $params['host'] ?? null;
                    if ($port = $params['port'] ?? null) {
                        $host .= ':' . $port;
                    }

                    // server, username and password for connecting to database
                    return [
                        $host,
                        $params['user'] ?? null,
                        $params['password'] ?? null,
                    ];
                }

                public function database(): string
                {
                    $db = \Pimcore\Db::get();
                    // database name, will be escaped by Adminer
                    return $db->getDatabase();
                }

                public function databases($flush = true)
                {
                    $cacheKey = 'pimcore_adminer_databases';

                    if (!$return = Cache::load($cacheKey)) {
                        $return = Db::getConnection()->fetchAllAssociative('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA');

                        foreach ($return as &$ret) {
                            $ret = $ret['SCHEMA_NAME'];
                        }

                        Cache::save($return, $cacheKey);
                    }

                    return $return;
                }
            }

            return new AdminerPimcore($plugins);
        }
    }
}
