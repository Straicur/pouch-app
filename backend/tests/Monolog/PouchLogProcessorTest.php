<?php

declare(strict_types = 1);

namespace App\Tests\Monolog;

use App\Entity\Pouch;
use App\Monolog\PouchLogProcessor;
use App\Services\Pouch\CurrentPouchResolverInterface;
use DateTimeImmutable;
use LogicException;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class PouchLogProcessorTest extends TestCase
{
    private function record(): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'test message',
        );
    }

    public function testAddsThePouchIdWhenOneCanBeResolved(): void
    {
        $pouch = $this->createStub(Pouch::class);
        $pouch->method('getId')->willReturn(7);

        $currentPouchResolver = $this->createStub(CurrentPouchResolverInterface::class);
        $currentPouchResolver->method('resolve')->willReturn($pouch);

        $processor = new PouchLogProcessor($currentPouchResolver);
        $result = $processor->__invoke($this->record());

        self::assertSame(7, $result->extra['pouchId']);
    }

    public function testLeavesTheRecordUntouchedOutsideAnAuthenticatedRequest(): void
    {
        $currentPouchResolver = $this->createStub(CurrentPouchResolverInterface::class);
        $currentPouchResolver->method('resolve')->willThrowException(new LogicException('No authenticated User to resolve a Pouch for.'));

        $processor = new PouchLogProcessor($currentPouchResolver);
        $record = $this->record();
        $result = $processor->__invoke($record);

        self::assertSame($record, $result);
        self::assertArrayNotHasKey('pouchId', $result->extra);
    }
}
