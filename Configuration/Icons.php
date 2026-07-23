<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-maispace-mai_seeder' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:mai_seeder/Resources/Public/Icons/Extension.svg',
    ],
];
