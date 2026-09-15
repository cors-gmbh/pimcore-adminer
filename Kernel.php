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

use CORS\Bundle\AdminerBundle\CORSAdminerBundle;
use Pimcore\HttpKernel\BundleCollection\BundleCollection;
use Pimcore\Kernel as PimcoreKernel;
use Symfony\WebpackEncoreBundle\WebpackEncoreBundle;

/**
 * Dev-harness kernel. Not part of the distributed composer package (see .gitattributes);
 * only autoloaded through autoload-dev.
 */
class Kernel extends PimcoreKernel
{
    public function registerBundlesToCollection(BundleCollection $collection): void
    {
        // The bundle under development is always on; everything else (Studio,
        // generic data index, …) is registered through config/bundles.php.
        $collection->addBundle(new CORSAdminerBundle());
        $collection->addBundle(new WebpackEncoreBundle());
    }
}
