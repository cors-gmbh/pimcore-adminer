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

use CORS\Bundle\AdminerBundle\lib\Pim\AdminerObjectFactory;

/*
 * Adminer's bootstrap calls a global adminer_object() to get its customized instance. The file is
 * loaded by DefaultController right before it includes Adminer, and the factory is configured there.
 */
if (!function_exists('adminer_object')) {
    function adminer_object(): Adminer\Plugins
    {
        return AdminerObjectFactory::create();
    }
}
