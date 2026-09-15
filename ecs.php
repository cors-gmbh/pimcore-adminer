<?php

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

declare(strict_types=1);

use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

// Public repository: the shared CORS tooling (cors/dev) lives on the private Packagist, which
// GitHub Actions cannot reach from here, so the public coreshop/test-setup rule set is used.
return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->import('vendor/coreshop/test-setup/ecs.php');
    $ecsConfig->parallel();
    $ecsConfig->paths(['src']);

    $header = <<<EOT
CORS GmbH

This source file is available under the MIT license

Full copyright and license information is available in
LICENSE.md which is distributed with this source code.

@copyright  Copyright (c) CORS GmbH (https://www.cors.gmbh)
@license    https://www.cors.gmbh/license MIT
 
EOT;

    $ecsConfig->ruleWithConfiguration(HeaderCommentFixer::class, ['header' => $header]);
};
