<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Clients\Domain;

use App\Modules\Clients\Infrastructure\Persistence\Doctrine\Entity\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClientTest extends TestCase
{
    public function testClientWithoutFullDataIsAnonymous(): void
    {
        $client = new Client($this->generateId(), 'anonymous@example.com');

        self::assertTrue($client->isAnonymous());
        self::assertTrue($client->hasContactData());
        self::assertNull($client->getFullName());
    }

    public function testIdentifyClientFillsMissingData(): void
    {
        $client = new Client($this->generateId(), null, '+48 123 456 789');

        $client->identify('john.doe@example.com', '+48 123 456 789', 'john', 'doe', $identifiedAt = new \DateTimeImmutable('-1 minute'));

        self::assertSame('john.doe@example.com', $client->getEmail());
        self::assertSame('+48123456789', $client->getPhone());
        self::assertSame('John', $client->getFirstName());
        self::assertSame('Doe', $client->getLastName());
        self::assertSame('John Doe', $client->getFullName());
        self::assertFalse($client->isAnonymous());
        self::assertSame($identifiedAt, $client->getIdentifiedAt());
    }

    public function testUpdateContactNormalizesValues(): void
    {
        $client = new Client($this->generateId(), 'john.doe@example.com', '+48 111 222 333', 'John', 'Doe');

        $client->updateContact(' JANE.SMITH@example.com ', '  +48 700 800 900  ');

        self::assertSame('jane.smith@example.com', $client->getEmail());
        self::assertSame('+48700800900', $client->getPhone());
        self::assertInstanceOf(\DateTimeImmutable::class, $client->getUpdatedAt());
    }

    public function testInvalidEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client($this->generateId(), 'wrong-email');
    }

    public function testInvalidPhoneThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client($this->generateId(), null, 'abc123');
    }

    public function testInvalidNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client($this->generateId(), null, null, 'J', 'D');
    }

    private function generateId(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
