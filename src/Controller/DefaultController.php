<?php

namespace CORS\Bundle\AdminerBundle\Controller {

    use CORS\Bundle\AdminerBundle\lib\Pim\Helper;
    use Pimcore\Logger;
    use Pimcore\Tool\Admin;
    use Pimcore\Tool\Session;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Event\ControllerEvent;
    use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
    use Symfony\Component\HttpKernel\Profiler\Profiler;
    use Symfony\Component\Routing\Annotation\Route;
    use Pimcore\Helper\Mail as MailHelper;

    class DefaultController
    {

        /**
         * @var string
         */
        protected $adminerHome = '';

        /**
         * @Route("/admin/CORSAdminerBundle/adminer", name="data_director_adminer")
         *
         * @return Response
         *
         */
        public function adminerAction(?Profiler $profiler, Request $request): Response
        {
            $this->prepare($request);

            if ($profiler) {
                $profiler->disable();
            }

            set_error_handler(function () {});

            chdir($this->adminerHome.'adminer');
            ob_start(static function ($html) {
                $html = MailHelper::setAbsolutePaths($html, null, Helper::getHostUrl(). '/admin/CORSAdminerBundle/adminer');
                $html = str_replace('static/editing.js', Helper::getHostUrl().'/admin/CORSAdminerBundle/adminer/static/editing.js', $html);
                return $html;
            });
            include($this->adminerHome.'adminer/index.php');

            @ob_get_flush();

            $response = new Response();

            return $this->mergeAdminerHeaders($response);
        }

        /**
         * @Route("/admin/CORSAdminerBundle/adminer/static/{path}", requirements={"path"=".*"})
         * @Route("/admin/CORSAdminerBundle/externals/{path}", requirements={"path"=".*"}, defaults={"type": "external"})
         * @param Request $request
         *
         * @return Response
         */
        public function proxyAction(Request $request): Response
        {
            $this->prepare($request);

            $response = new Response();
            $content = '';

            // proxy for resources
            $path = $request->get('path');

            if (preg_match('@\.(css|js|ico|png|jpg|gif)$@', $path)) {
                if ($request->get('type') === 'external') {
                    $path = '../'.$path;
                }

                if (strpos($path, 'static/') === 0) {
                    $path = 'adminer/'.$path;
                }

                $filePath = $this->adminerHome.'/'.$path;
                if (!file_exists($filePath)) {
                    $filePath = $this->adminerHome.'adminer/static/'.$path;
                }
                // it seems that css files need the right content-type (Chrome)
                if (preg_match('@.css$@', $path)) {
                    $response->headers->set('Content-Type', 'text/css');
                } elseif (preg_match('@.js$@', $path)) {
                    $response->headers->set('Content-Type', 'text/javascript');
                }

                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);

                    if (preg_match('@default.css$@', $path)) {
                        // append custom styles, because in Adminer everything is hardcoded
                        $content .= file_get_contents($this->adminerHome.'designs/konya/adminer.css');
                        $content .= file_get_contents(__DIR__.'/../Resources/public/css/adminer-modifications.css');
                    }
                }
            }

            $response->setContent($content);

            return $this->mergeAdminerHeaders($response);
        }

        /**
         * @param ControllerEvent $event
         *
         * @return void
         */
        public function prepare(): void
        {
            // PHP 7.0 compatibility of adminer (throws some warnings)
            ini_set('display_errors', 0);

            $this->checkPermission('system_settings');

             //call this to keep the session 'open' so that Adminer can write to it
            if(method_exists(Session::class, 'get')) {
                if(method_exists(Session::class, 'get')) {
                    $session = Session::get();
                }
            }

            $this->adminerHome = PIMCORE_COMPOSER_PATH.'/vrana/adminer/';
        }

        /**
         * Check user permission
         *
         * @param string $permission
         *
         * @throws AccessDeniedHttpException
         */
        protected function checkPermission($permission)
        {
            $user = Admin::getCurrentUser();

            if (!$user || !$user->isAllowed($permission)) {
                Logger::error(
                    'User {user} attempted to access {permission}, but has no permission to do so',
                    [
                        'user' => $user ? $user->getName() : null,
                        'permission' => 'system_settings',
                    ]
                );

                throw new AccessDeniedHttpException('Access denied. User has no permission to access "'.$permission.'".');
            }
        }

        /**
         * Merges http-headers set from Adminer via headers function
         * to the Symfony Response Object
         *
         * @param Response $response
         *
         * @return Response
         */
        protected function mergeAdminerHeaders(Response $response)
        {
            if (!headers_sent()) {
                $headersRaw = headers_list();

                foreach ($headersRaw as $header) {
                    $header = explode(':', $header, 2);
                    list($headerKey, $headerValue) = $header;

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
    use CORS\Bundle\AdminerBundle\Model\PimcoreDbRepository;
    use Pimcore\Cache;
    use Pimcore\Tool\Session;

    if (!function_exists('adminer_object')) {
        // adminer plugin
        /**
         * @return AdminerPimcore
         */
        function adminer_object()
        {
            $pluginDir = PIMCORE_COMPOSER_PATH.'/vrana/adminer/plugins';

            // required to run any plugin
            include_once $pluginDir.'/plugin.php';

            // autoloader
            foreach (glob($pluginDir.'/*.php') as $filename) {
                include_once $filename;
            }

            $plugins = [
                new \CORS\Bundle\AdminerBundle\lib\Pim\AdminerPlugins(),
                new \AdminerFrames(),
                new \AdminerDumpDate,
                new \AdminerDumpJson,
                new \AdminerDumpBz2,
                new \AdminerDumpZip,
                new \AdminerDumpXml,
                new \AdminerDumpAlter
            ];

            // support for SSL (at least for PDO)
            $driverOptions = \Pimcore\Db::get()->getParams()['driverOptions'] ?? [];
            $ssl = [
                'key' => $driverOptions[\PDO::MYSQL_ATTR_SSL_KEY] ?? null,
                'cert' => $driverOptions[\PDO::MYSQL_ATTR_SSL_CERT] ?? null,
                'ca' => $driverOptions[\PDO::MYSQL_ATTR_SSL_CA] ?? null,
            ];
            if ($ssl['key'] !== null || $ssl['cert'] !== null || $ssl['ca'] !== null) {
                $plugins[] = new \AdminerLoginSsl($ssl);
            }

            class AdminerPimcore extends \AdminerPlugin
            {
                /**
                 * @return string
                 */
                public function name()
                {
                    return '';
                }

                public function loginForm()
                {
                    parent::loginForm();
                    echo '<script'.nonce().">document.querySelector('input[name=auth\\\\[db\\\\]]').value='".$this->database()."'; document.querySelector('form').submit()</script>";
                }

                /**
                 * @param bool $create
                 *
                 * @return string
                 */
                public function permanentLogin($create = false)
                {
                    if(method_exists(Session::class, 'getSessionId')) {
                        return Session::getSessionId();
                    }
                }

                /**
                 * @param string $login
                 * @param string $password
                 *
                 * @return bool
                 */
                public function login($login, $password)
                {
                    return true;
                }

                /**
                 * @return array
                 */
                public function credentials()
                {
                    $params = \Pimcore\Db::get()->getParams();

                    $host = $params['host'] ?? null;
                    if ($port = $params['port'] ?? null) {
                        $host .= ':'.$port;
                    }

                    // server, username and password for connecting to database
                    $result = [
                        $host,
                        $params['user'] ?? null,
                        $params['password'] ?? null,
                    ];

                    return $result;
                }

                /**
                 * @return string
                 */
                public function database()
                {
                    $db = \Pimcore\Db::get();
                    // database name, will be escaped by Adminer
                    return $db->getDatabase();
                }

                public function databases($flush = true)
                {
                    $cacheKey = 'pimcore_adminer_databases';

                    if (!$return = Cache::load($cacheKey)) {
                        $return = PimcoreDbRepository::getInstance()->findInSql('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA');

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