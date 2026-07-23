<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Controller\Backend;

use Maispace\MaiBase\Controller\Backend\AbstractBackendController;
use Maispace\MaiSeeder\Migration\MigrationRunner;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Admin-only backend module listing every discovered migration and its
 * status - modelled after the Install Tool's Upgrade Wizards module. Reuses
 * MigrationRunner, the same service the CLI commands talk to.
 */
#[AsController]
final class MigrationModuleController extends AbstractBackendController
{
    private const string EXTENSION_NAME = 'MaiSeeder';

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        IconFactory $iconFactory,
        private readonly MigrationRunner $runner,
    ) {
        parent::__construct($moduleTemplateFactory, $iconFactory);
    }

    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->createModuleTemplate();
        $this->addShortcutButton(
            $moduleTemplate,
            'mai_seeder',
            $this->translate('module.title') ?? 'Content Migrations',
        );
        $this->assignMultiple($moduleTemplate, [
            'statuses' => $this->runner->status(),
        ]);

        return $this->renderModuleResponse($moduleTemplate, 'Index');
    }

    public function executeAction(string $identifier = ''): ResponseInterface
    {
        try {
            $result = $this->runner->migrateOne($identifier);
            if ($result->success) {
                $this->flashSuccess(
                    $result->identifier,
                    $this->translate('flash.executed.title') ?? 'Migration executed',
                );
            } else {
                $this->flashError(
                    (string)$result->errorMessage,
                    $this->translate('flash.executeFailed.title') ?? 'Migration failed',
                );
            }
        } catch (\Throwable $e) {
            $this->flashError(
                $e->getMessage(),
                $this->translate('flash.executeFailed.title') ?? 'Migration failed',
            );
        }

        return $this->redirect('index');
    }

    public function rollbackAction(string $identifier = ''): ResponseInterface
    {
        try {
            $result = $this->runner->rollbackOne($identifier);
            if ($result->success) {
                $this->flashSuccess(
                    $result->identifier,
                    $this->translate('flash.rolledBack.title') ?? 'Migration rolled back',
                );
            } else {
                $this->flashError(
                    (string)$result->errorMessage,
                    $this->translate('flash.rollbackFailed.title') ?? 'Rollback failed',
                );
            }
        } catch (\Throwable $e) {
            $this->flashError(
                $e->getMessage(),
                $this->translate('flash.rollbackFailed.title') ?? 'Rollback failed',
            );
        }

        return $this->redirect('index');
    }

    private function translate(string $key): ?string
    {
        return LocalizationUtility::translate($key, self::EXTENSION_NAME);
    }
}
