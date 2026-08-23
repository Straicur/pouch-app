<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SystemKernelTestCase extends KernelTestCase
{
    protected ?object $entityManager = null;
    protected ?DatabaseMockManager $databaseMockManager = null;
    protected Application $commandApplication;

    protected function setUp(): void
    {
        if (!self::$booted) {
            self::bootKernel(['environment' => 'test']);
        }

        if ($this->databaseMockManager === null) {
            $this->databaseMockManager = new DatabaseMockManager(static::getContainer());
        }

        $this->commandApplication = new Application(self::$kernel);

        $this->entityManager = self::$kernel->getContainer()->get('doctrine.orm.entity_manager');

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollback();
        }

        parent::tearDown();
    }

    protected function getService(string $serviceName): ?object
    {
        return self::$kernel->getContainer()->get($serviceName);
    }
}
