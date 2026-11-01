<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Rasuvaeff\JsonLd\Loader\PsrContextLoader;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(PsrContextLoader::class)]
#[Covers(LoadedContext::class)]
#[Covers(Json::class)]
final class PsrContextLoaderTest
{
    public function urlPolicyIsConsultedBeforeAnyRequest(): void
    {
        $client = new class implements ClientInterface {
            public int $sent = 0;

            #[\Override]
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->sent++;

                throw new \LogicException('must not be called');
            }
        };

        $factory = new class implements RequestFactoryInterface {
            #[\Override]
            public function createRequest(string $method, $uri): RequestInterface
            {
                throw new \LogicException('must not be called');
            }
        };

        $loader = new PsrContextLoader($client, $factory, urlPolicy: static fn(string $url): bool => str_starts_with($url, 'https://trusted.example/'));

        $refused = false;

        try {
            $loader->load('https://evil.example/ctx');
        } catch (DocumentLoadingException) {
            $refused = true;
        }

        Assert::true($refused);
        Assert::same($client->sent, 0);
    }

    public function nonHttpSchemesAreRefused(): void
    {
        $loader = new PsrContextLoader(
            new class implements ClientInterface {
                #[\Override]
                public function sendRequest(RequestInterface $request): ResponseInterface
                {
                    throw new \LogicException('must not be called');
                }
            },
            new class implements RequestFactoryInterface {
                #[\Override]
                public function createRequest(string $method, $uri): RequestInterface
                {
                    throw new \LogicException('must not be called');
                }
            },
        );

        $refused = false;

        try {
            $loader->load('file:///etc/passwd');
        } catch (DocumentLoadingException) {
            $refused = true;
        }

        Assert::true($refused);
    }
}
