<?php

declare(strict_types=1);

use Maispace\MaiSeeder\Controller\Backend\MigrationModuleController;

return [
    'mai_seeder' => [
        'parent' => 'system',
        'access' => 'admin',
        'workspaces' => 'online',
        'path' => '/module/system/mai-seeder',
        'iconIdentifier' => 'mai-backend-module',
        'labels' => 'LLL:EXT:mai_seeder/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'MaiSeeder',
        'controllerActions' => [
            MigrationModuleController::class => ['index', 'execute', 'rollback'],
        ],
    ],
];
