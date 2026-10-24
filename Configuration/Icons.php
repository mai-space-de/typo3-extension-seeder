<?php

declare(strict_types=1);

$allIcons = 'EXT:mai_base/Resources/Public/Icons/all-icons/';

return [
    'ext-maispace-mai_seeder' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        'source' => 'EXT:mai_seeder/Resources/Public/Icons/Extension.svg',
    ],
    'mai-seeder-module' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        'source' => $allIcons . 'interface-arrows-synchronize--arrows-loading-load-sync-synchronize-arrow-reload--Streamline-Core.svg',
    ],
];
