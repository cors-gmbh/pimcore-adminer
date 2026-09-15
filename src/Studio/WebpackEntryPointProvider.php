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

namespace CORS\Bundle\AdminerBundle\Studio;

use Pimcore\Bundle\StudioUiBundle\Build\BuildArchive;
use Pimcore\Bundle\StudioUiBundle\Build\BuildArchiveExtractionTrait;
use Pimcore\Bundle\StudioUiBundle\Build\BuildArchiveProviderInterface;

/**
 * The Studio build is shipped as Resources/build-dist/build-<id>.zip and unpacked into
 * Resources/public/studio by Pimcore's BuildArchiveExtractor at cache warmup
 * (pimcore/studio-ui-bundle#3779). The extractor is injected through the trait's
 * #[Required] setter (see the explicit call in services.yaml, the service is not autowired).
 */
final class WebpackEntryPointProvider implements BuildArchiveProviderInterface
{
    use BuildArchiveExtractionTrait;

    /**
     * BuildArchive is marked internal, but it is the only way to hook into Pimcore's build extraction.
     *
     * @psalm-suppress InternalClass, InternalMethod
     */
    protected function buildArchive(): BuildArchive
    {
        return new BuildArchive(
            archiveGlob: __DIR__ . '/../Resources/build-dist/build*.zip',
            targetDir: __DIR__ . '/../Resources/public/studio',
        );
    }

    public function getEntryPoints(): array
    {
        return ['exposeRemote'];
    }

    public function getOptionalEntryPoints(): array
    {
        return [];
    }
}
