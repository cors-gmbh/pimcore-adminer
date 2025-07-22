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

namespace CORS\Bundle\AdminerBundle;

use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class Installer extends SettingsStoreAwareInstaller
{
    public function __construct(
        protected BundleInterface $bundle,
    ) {
        parent::__construct($bundle);
    }

    public function install(): void
    {
        parent::install();
    }

    public function uninstall(): void
    {
        parent::uninstall();
    }
}
