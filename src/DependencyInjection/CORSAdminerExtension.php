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

namespace CORS\Bundle\AdminerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @see http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class CORSAdminerExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @inheritdoc
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configs = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $loader->load('services.yaml');
    }

    /**
     * Adminer posts its own forms and cannot carry Pimcore's admin CSRF token, so the route
     * has to be excluded from the admin CSRF check.
     *
     * The exclusion is prepended instead of shipped as static config because the
     * `pimcore_admin` extension comes with pimcore/admin-ui-classic-bundle, which no longer
     * exists in Studio-only installations (Pimcore 2026 dropped the classic admin). A static
     * `pimcore_admin:` key would make the container fail to compile there.
     */
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('pimcore_admin')) {
            return;
        }

        $container->prependExtensionConfig('pimcore_admin', [
            'csrf_protection' => [
                'excluded_routes' => ['cors_adminer'],
            ],
        ]);
    }
}
