<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(Iri::class)]
final class IriTest
{
    public function parsesComponents(): void
    {
        $iri = Iri::of('https://user:pass@example.com:8080/path/to;x?a=1&b=2#frag');

        Assert::same($iri->scheme, 'https');
        Assert::same($iri->userInfo, 'user:pass');
        Assert::same($iri->host, 'example.com');
        Assert::same($iri->port, '8080');
        Assert::same($iri->path, '/path/to;x');
        Assert::same($iri->query, 'a=1&b=2');
        Assert::same($iri->fragment, 'frag');
        Assert::true($iri->isAbsolute());
        Assert::same((string) $iri, 'https://user:pass@example.com:8080/path/to;x?a=1&b=2#frag');
    }

    public function distinguishesAbsentAndEmptyQueryAndFragment(): void
    {
        $plain = Iri::of('http://example.com/a');

        Assert::null($plain->query);
        Assert::null($plain->fragment);

        $emptyQuery = Iri::of('http://example.com/a?');

        Assert::same($emptyQuery->query, '');
        Assert::null($emptyQuery->fragment);

        $emptyFragment = Iri::of('http://example.com/a?#');

        Assert::same($emptyFragment->query, '');
        Assert::same($emptyFragment->fragment, '');
    }

    public function parsesEmptyAuthority(): void
    {
        $iri = Iri::of('file:///tmp/x');

        Assert::same($iri->scheme, 'file');
        Assert::same($iri->host, '');
        Assert::same($iri->path, '/tmp/x');
    }

    public function parsesIpv6HostWithPort(): void
    {
        $iri = Iri::of('http://[2001:db8::1]:443/x');

        Assert::same($iri->host, '[2001:db8::1]');
        Assert::same($iri->port, '443');
    }

    #[DataProvider('rfc3986Section54Provider')]
    public function resolvesRfc3986References(string $reference, string $expected): void
    {
        $base = Iri::of('http://a/b/c/d;p?q');

        Assert::same((string) $base->resolve(Iri::of($reference)), $expected);
    }

    public static function rfc3986Section54Provider(): iterable
    {
        // RFC 3986 section 5.4.1 (normal cases)
        yield 'g:h' => ['g:h', 'g:h'];
        yield 'g' => ['g', 'http://a/b/c/g'];
        yield './g' => ['./g', 'http://a/b/c/g'];
        yield 'g/' => ['g/', 'http://a/b/c/g/'];
        yield '/g' => ['/g', 'http://a/g'];
        yield '//g' => ['//g', 'http://g'];
        yield '?y' => ['?y', 'http://a/b/c/d;p?y'];
        yield 'g?y' => ['g?y', 'http://a/b/c/g?y'];
        yield '#s' => ['#s', 'http://a/b/c/d;p?q#s'];
        yield 'g#s' => ['g#s', 'http://a/b/c/g#s'];
        yield 'g?y#s' => ['g?y#s', 'http://a/b/c/g?y#s'];
        yield ';x' => [';x', 'http://a/b/c/;x'];
        yield 'g;x' => ['g;x', 'http://a/b/c/g;x'];
        yield 'g;x?y#s' => ['g;x?y#s', 'http://a/b/c/g;x?y#s'];
        yield 'empty' => ['', 'http://a/b/c/d;p?q'];
        yield '.' => ['.', 'http://a/b/c/'];
        yield './' => ['./', 'http://a/b/c/'];
        yield '..' => ['..', 'http://a/b/'];
        yield '../' => ['../', 'http://a/b/'];
        yield '../g' => ['../g', 'http://a/b/g'];
        yield '../..' => ['../..', 'http://a/'];
        yield '../../' => ['../../', 'http://a/'];
        yield '../../g' => ['../../g', 'http://a/g'];

        // RFC 3986 section 5.4.2 (abnormal cases)
        yield '../../../g' => ['../../../g', 'http://a/g'];
        yield '../../../../g' => ['../../../../g', 'http://a/g'];
        yield '/./g' => ['/./g', 'http://a/g'];
        yield '/../g' => ['/../g', 'http://a/g'];
        yield 'g.' => ['g.', 'http://a/b/c/g.'];
        yield '.g' => ['.g', 'http://a/b/c/.g'];
        yield 'g..' => ['g..', 'http://a/b/c/g..'];
        yield '..g' => ['..g', 'http://a/b/c/..g'];
        yield './../g' => ['./../g', 'http://a/b/g'];
        yield './g/.' => ['./g/.', 'http://a/b/c/g/'];
        yield 'g/./h' => ['g/./h', 'http://a/b/c/g/h'];
        yield 'g/../h' => ['g/../h', 'http://a/b/c/h'];
        yield 'g;x=1/./y' => ['g;x=1/./y', 'http://a/b/c/g;x=1/y'];
        yield 'g;x=1/../y' => ['g;x=1/../y', 'http://a/b/c/y'];
        yield 'g?y/./x' => ['g?y/./x', 'http://a/b/c/g?y/./x'];
        yield 'g?y/../x' => ['g?y/../x', 'http://a/b/c/g?y/../x'];
        yield 'g#s/./x' => ['g#s/./x', 'http://a/b/c/g#s/./x'];
        yield 'g#s/../x' => ['g#s/../x', 'http://a/b/c/g#s/../x'];
        yield 'http:g' => ['http:g', 'http:g'];
    }

    #[Property(runs: 300)]
    public function absoluteReferenceKeepsIdentity(string $reference, string $base): void
    {
        $resolved = Iri::of($base)->resolve(Iri::of($reference));

        Assert::same((string) $resolved, $reference);
    }

    /** @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface> */
    public static function absoluteReferenceKeepsIdentityGenerators(): array
    {
        return [
            'reference' => self::absoluteIri(),
            'base' => self::absoluteIri(),
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function absoluteReferenceKeepsIdentityExamples(): iterable
    {
        yield 'http' => ['http://example.com/a/../b', 'https://base/x'];
        yield 'urn' => ['urn:isbn:0451450523', 'http://base/'];
        yield 'with query and fragment' => ['http://x/y?z#w', 'http://b/'];
    }

    #[Property(runs: 300)]
    public function resolvedPathHasNoDotSegments(string $reference, string $base): void
    {
        $resolved = Iri::of($base)->resolve(Iri::of($reference));
        $segments = array_filter(explode('/', $resolved->path), static fn (string $s): bool => $s !== '');

        Assert::false(in_array('.', $segments, true));
        Assert::false(in_array('..', $segments, true));
    }

    /** @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface> */
    public static function resolvedPathHasNoDotSegmentsGenerators(): array
    {
        $segments = Gen::frequency([
            [3, Gen::stringFrom('abcdefghijklmnopqrstuvwxyz0123456789')],
            [1, Gen::elements(['.', '..'])],
            [1, Gen::constant('')],
        ]);

        $path = Gen::map(
            static fn (array $parts): string => implode('/', $parts),
            Gen::nonEmptyArrayOf($segments, maxSize: 4),
        );

        return [
            'reference' => Gen::frequency([[1, $path], [1, Gen::map($path, static fn (string $p): string => '/' . $p)]]),
            'base' => self::absoluteIri(),
        ];
    }

    #[Property(runs: 200)]
    public function parseAndToStringAreIdempotent(string $value): void
    {
        $first = (string) Iri::of($value);

        Assert::same((string) Iri::of($first), $first);
    }

    /** @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface> */
    public static function parseAndToStringAreIdempotentGenerators(): array
    {
        return ['value' => self::absoluteIri()];
    }

    private static function absoluteIri(): \Rasuvaeff\PropertyTesting\ArbitraryInterface
    {
        $host = Gen::map(
            static fn (array $parts): string => implode('.', $parts),
            Gen::nonEmptyArrayOf(Gen::stringFrom('abcdefghij', maxSize: 8), minLength: 1, maxLength: 3),
        );

        $segment = Gen::stringFrom('abcdefghij0123456789;=,', minLength: 0, maxLength: 6);

        $path = Gen::map(
            static fn (array $parts): string => '/' . implode('/', $parts),
            Gen::arrayOf($segment, minSize: 0, maxSize: 3),
        );

        $query = Gen::nullable(Gen::stringFrom('abc012=&', minLength: 0, maxLength: 6));

        $fragment = Gen::nullable(Gen::stringFrom('abc012', minLength: 0, maxLength: 4));

        return Gen::flatMap(
            static fn (array $parts): \Rasuvaeff\PropertyTesting\ArbitraryInterface => Gen::constant(
                'http://' . $parts['host'] . $parts['path']
                . ($parts['query'] !== null ? '?' . $parts['query'] : '')
                . ($parts['fragment'] !== null ? '#' . $parts['fragment'] : ''),
            ),
            Gen::record(['host' => $host, 'path' => $path, 'query' => $query, 'fragment' => $fragment]),
        );
    }
}
