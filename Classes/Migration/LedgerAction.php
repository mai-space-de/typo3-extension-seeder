<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration;

enum LedgerAction: string
{
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';
}
