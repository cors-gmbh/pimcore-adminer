<?php

declare(strict_types=1);

namespace CORS\Bundle\AdminerBundle\Studio;

use Pimcore\Bundle\StudioUiBundle\Webpack\WebpackEntryPointProviderInterface;

final class WebpackEntryPointProvider implements WebpackEntryPointProviderInterface
{
    public function getEntryPointsJsonLocations(): array
    {
        return glob(__DIR__ . '/../Resources/public/build/*/entrypoints.json');
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
