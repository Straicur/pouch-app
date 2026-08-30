<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Cookie;

abstract class WebTest extends WebTestCase
{
    protected KernelBrowser $webClient;
    protected ?object $entityManager;
    protected ?DatabaseMockManager $databaseMockManager = null;
    protected ?TestTool $responseTool = null;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->webClient = static::createClient(['environment' => 'test']);
        $this->webClient->enableProfiler();

        $this->databaseMockManager = new DatabaseMockManager(static::getContainer());
        $this->responseTool = new TestTool('AbstractWebTest');

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollback();
        }

        $application = new Application();
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'cache:pool:clear',
            '--all' => true,
        ]);
        $application->run($input, new BufferedOutput());

        $input = new ArrayInput([
            'command' => 'doctrine:cache:clear-metadata'
        ]);
        $application->run($input, new BufferedOutput());

    }

    protected function getService(string $serviceName): object
    {
        return $this->webClient->getContainer()->get($serviceName);
    }

    protected function getCookieValueFromJar(string $name): ?string
    {
        return $this->webClient->getCookieJar()->get($name)?->getValue();
    }

    /**
     * Puts a cookie minted via DatabaseMockManager::loginUser() into the client's
     * cookie jar, so it's resent automatically on every subsequent request — the
     * `server: [CookieService::ACCESS_TOKEN => ...]` one-off trick only survives a
     * single request.
     */
    protected function setAuthCookie(Cookie $cookie): void
    {
        $this->webClient->getCookieJar()->set(
            new BrowserKitCookie($cookie->getName(), $cookie->getValue())
        );
    }
}
