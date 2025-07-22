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

return static function (\Symplify\EasyCodingStandard\Config\ECSConfig $ecsConfig): void {
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

    $ecsConfig->ruleWithConfiguration(\PhpCsFixer\Fixer\Comment\HeaderCommentFixer::class, ['header' => $header]);
};
