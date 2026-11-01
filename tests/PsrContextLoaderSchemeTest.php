<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Rasuvaeff\JsonLd\Loader\PsrContextLoader;
use Rasuvaeff\JsonLd\Tests\Support\ReachedRequestFactory;
use Rasuvaeff\JsonLd\Tests\Support\UnusedHttpClient;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(PsrContextLoader::class)]
#[Covers(LoadedContext::class)]
#[Covers(Json::class)]
final class PsrContextLoaderSchemeTest
{
    public function acceptsHttpAndHttpsInAnyCaseWithoutAPolicy(): void
    {
        $loader = new PsrContextLoader(new UnusedHttpClient(), new ReachedRequestFactory());

        foreach (['http://example.com/c', 'https://example.com/c', 'HTTP://example.com/c', 'HtTpS://example.com/c'] as $url) {
            Assert::same($this->outcome($loader, $url), ReachedRequestFactory::MESSAGE);
        }
    }

    public function refusesOtherSchemesAndRelativeReferences(): void
    {
        $loader = new PsrContextLoader(new UnusedHttpClient(), new ReachedRequestFactory());

        foreach (['ftp://example.com/c', 'file:///etc/passwd', 'example.com/c', '/c', ''] as $url) {
            Assert::same($this->outcome($loader, $url), DocumentLoadingException::class);
        }
    }

    public function consultsThePolicyForAllowedSchemes(): void
    {
        $loader = new PsrContextLoader(
            new UnusedHttpClient(),
            new ReachedRequestFactory(),
            urlPolicy: static fn(string $url): bool => str_ends_with($url, '/ok'),
        );

        Assert::same($this->outcome($loader, 'https://example.com/ok'), ReachedRequestFactory::MESSAGE);
        Assert::same($this->outcome($loader, 'https://example.com/no'), DocumentLoadingException::class);
    }

    private function outcome(PsrContextLoader $loader, string $url): string
    {
        try {
            $loader->load($url);
        } catch (DocumentLoadingException) {
            return DocumentLoadingException::class;
        } catch (\LogicException $exception) {
            return $exception->getMessage();
        }

        return 'loaded';
    }
}
