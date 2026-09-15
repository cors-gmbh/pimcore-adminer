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

namespace App\InstallProfile;

use Pimcore\Bundle\ApplicationLoggerBundle\PimcoreApplicationLoggerBundle;
use Pimcore\Bundle\GenericDataIndexBundle\PimcoreGenericDataIndexBundle;
use Pimcore\Bundle\GenericExecutionEngineBundle\PimcoreGenericExecutionEngineBundle;
use Pimcore\Bundle\InstallBundle\EnvVarDefinition\Definitions\DatabaseEnvVarDefinition;
use Pimcore\Bundle\InstallBundle\EnvVarDefinition\Definitions\DoctrineMessengerEnvVarDefinition;
use Pimcore\Bundle\InstallBundle\EnvVarDefinition\Definitions\MercureEnvVarDefinition;
use Pimcore\Bundle\InstallBundle\EnvVarDefinition\Definitions\OpenSearchEnvVarDefinition;
use Pimcore\Bundle\InstallBundle\Profile\DataSource\DataSourceInterface;
use Pimcore\Bundle\InstallBundle\Profile\InstallProfileInterface;
use Pimcore\Bundle\InstallBundle\Profile\PostInstallCommand;
use Pimcore\Bundle\OpenSearchClientBundle\PimcoreOpenSearchClientBundle;
use Pimcore\Bundle\StudioBackendBundle\PimcoreStudioBackendBundle;
use Pimcore\Bundle\StudioUiBundle\PimcoreStudioUiBundle;

/**
 * Install profile for the cors/adminer development harness.
 *
 * Studio only — the classic admin bundle is deliberately not part of the harness, so the
 * Adminer widget in Studio and its controller under the Studio API prefix are what gets exercised locally.
 * The infrastructure defaults come from .env (dev-compose service names).
 */
final readonly class StudioInstallProfile implements InstallProfileInterface
{
    public function getName(): string
    {
        return 'cors-adminer-studio';
    }

    public function getDescription(): string
    {
        return 'Pimcore Studio harness for cors/adminer (Adminer as Studio widget)';
    }

    public function getBundles(): array
    {
        return [
            PimcoreApplicationLoggerBundle::class,
            PimcoreGenericExecutionEngineBundle::class,
            PimcoreOpenSearchClientBundle::class,
            PimcoreGenericDataIndexBundle::class,
            PimcoreStudioBackendBundle::class,
            PimcoreStudioUiBundle::class,
        ];
    }

    public function getEnvVarDefinitions(): array
    {
        return [
            new DatabaseEnvVarDefinition(),
            new OpenSearchEnvVarDefinition(),
            new MercureEnvVarDefinition(),
            // The dev php image has no ext-amqp, so messages go through the DB.
            new DoctrineMessengerEnvVarDefinition(),
        ];
    }

    public function getDataSource(): ?DataSourceInterface
    {
        return null;
    }

    public function getPostInstallCommands(): array
    {
        return [
            new PostInstallCommand(
                'generic-data-index:update:index',
                'Creating the search index mappings',
                priority: 10,
            ),
        ];
    }
}
