<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Controller\Backend;

use Maispace\MaiSeeder\Migration\MigrationRunner;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Admin-only backend module listing every discovered migration and its
 * status - modelled after the Install Tool's Upgrade Wizards module. Reuses
 * MigrationRunner, the same service the CLI commands talk to.
 */
#[AsController]
final class MigrationModuleController
{
    use AllowedMethodsTrait;

    private const ROUTE = 'mai_seeder_migrations';
    private const LANGUAGE_FILE = 'LLL:EXT:mai_seeder/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly MigrationRunner $runner,
        private readonly UriBuilder $uriBuilder,
        private readonly FlashMessageService $flashMessageService,
    ) {
    }

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAllowedHttpMethod($request, 'GET');

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assign('statuses', $this->runner->status());

        return $moduleTemplate->renderResponse('Migration/Index');
    }

    public function executeAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAllowedHttpMethod($request, 'POST');
        $identifier = (string)($request->getParsedBody()['identifier'] ?? '');

        try {
            $result = $this->runner->migrateOne($identifier);
            $this->addFlashMessage(
                $result->success,
                $result->success ? 'flash.executed.title' : 'flash.executeFailed.title',
                $result->success ? $result->identifier : (string)$result->errorMessage,
            );
        } catch (\Throwable $e) {
            $this->addFlashMessage(false, 'flash.executeFailed.title', $e->getMessage());
        }

        return $this->redirectToIndex();
    }

    public function rollbackAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAllowedHttpMethod($request, 'POST');
        $identifier = (string)($request->getParsedBody()['identifier'] ?? '');

        try {
            $result = $this->runner->rollbackOne($identifier);
            $this->addFlashMessage(
                $result->success,
                $result->success ? 'flash.rolledBack.title' : 'flash.rollbackFailed.title',
                $result->success ? $result->identifier : (string)$result->errorMessage,
            );
        } catch (\Throwable $e) {
            $this->addFlashMessage(false, 'flash.rollbackFailed.title', $e->getMessage());
        }

        return $this->redirectToIndex();
    }

    private function redirectToIndex(): ResponseInterface
    {
        return new RedirectResponse($this->uriBuilder->buildUriFromRoute(self::ROUTE));
    }

    private function addFlashMessage(bool $success, string $titleKey, string $message): void
    {
        $flashMessage = new FlashMessage(
            $message,
            $this->translate($titleKey),
            $success ? ContextualFeedbackSeverity::OK : ContextualFeedbackSeverity::ERROR,
            true,
        );
        $this->flashMessageService->getMessageQueueByIdentifier()->addMessage($flashMessage);
    }

    private function translate(string $key): string
    {
        return $GLOBALS['LANG']->sL(self::LANGUAGE_FILE . $key) ?: $key;
    }
}
