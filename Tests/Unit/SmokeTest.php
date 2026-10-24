<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Tests\Unit;

use Maispace\MaiSeeder\Migration\LedgerAction;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testLedgerActionEnumIsAvailable(): void
    {
        self::assertNotEmpty(LedgerAction::cases());
    }
}
