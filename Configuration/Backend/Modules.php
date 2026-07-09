<?php

declare(strict_types=1);

use Maispace\MaiSeeder\Controller\Backend\MigrationModuleController;

return [
    'mai_seeder_migrations' => [
        'parent' => 'system',
        'position' => [],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/system/mai-seeder-migrations',
        'iconIdentifier' => 'mai-seeder-module',
        'labels' => 'LLL:EXT:mai_seeder/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => MigrationModuleController::class . '::indexAction',
            ],
            'execute' => [
                'target' => MigrationModuleController::class . '::executeAction',
            ],
            'rollback' => [
                'target' => MigrationModuleController::class . '::rollbackAction',
            ],
        ],
    ],
];
