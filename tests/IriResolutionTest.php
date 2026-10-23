<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\Tests\Support\Strict;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Iri::class)]
final class IriResolutionTest
{
    private const array RFC3986_REFERENCES = [
        'g:h' => 'g:h',
        'g' => 'http://a/b/c/g',
        './g' => 'http://a/b/c/g',
        'g/' => 'http://a/b/c/g/',
        '/g' => 'http://a/g',
        '//g' => 'http://g',
        '?y' => 'http://a/b/c/d;p?y',
        'g?y' => 'http://a/b/c/g?y',
        '#s' => 'http://a/b/c/d;p?q#s',
        'g#s' => 'http://a/b/c/g#s',
        'g?y#s' => 'http://a/b/c/g?y#s',
        ';x' => 'http://a/b/c/;x',
        'g;x' => 'http://a/b/c/g;x',
        'g;x?y#s' => 'http://a/b/c/g;x?y#s',
        '' => 'http://a/b/c/d;p?q',
        '.' => 'http://a/b/c/',
        './' => 'http://a/b/c/',
        '..' => 'http://a/b/',
        '../' => 'http://a/b/',
        '../g' => 'http://a/b/g',
        '../..' => 'http://a/',
        '../../' => 'http://a/',
        '../../g' => 'http://a/g',
        '../../../g' => 'http://a/g',
        '../../../../g' => 'http://a/g',
        '/./g' => 'http://a/g',
        '/../g' => 'http://a/g',
        'g.' => 'http://a/b/c/g.',
        '.g' => 'http://a/b/c/.g',
        'g..' => 'http://a/b/c/g..',
        '..g' => 'http://a/b/c/..g',
        './../g' => 'http://a/b/g',
        './g/.' => 'http://a/b/c/g/',
        'g/./h' => 'http://a/b/c/g/h',
        'g/../h' => 'http://a/b/c/h',
        'g;x=1/./y' => 'http://a/b/c/g;x=1/y',
        'g;x=1/../y' => 'http://a/b/c/y',
        'g?y/./x' => 'http://a/b/c/g?y/./x',
        'g?y/../x' => 'http://a/b/c/g?y/../x',
        'g#s/./x' => 'http://a/b/c/g#s/./x',
        'g#s/../x' => 'http://a/b/c/g#s/../x',
    ];

    public function resolvesEveryRfc3986Reference(): void
    {
        $base = Iri::of('http://a/b/c/d;p?q');

        foreach (self::RFC3986_REFERENCES as $reference => $expected) {
            Assert::same((string) $base->resolve(Iri::of((string) $reference)), $expected);
        }
    }

    public function mergesAgainstAnAuthorityWithoutPath(): void
    {
        Assert::same((string) Iri::of('http://a')->resolve(Iri::of('b')), 'http://a/b');
        Assert::same((string) Iri::of('http://a')->resolve(Iri::of('b/../c')), 'http://a/c');
    }

    public function mergesAgainstPathsWithoutAuthority(): void
    {
        Assert::same((string) Iri::of('foo:bar')->resolve(Iri::of('baz')), 'foo:baz');
        Assert::same((string) Iri::of('foo:bar/baz')->resolve(Iri::of('x')), 'foo:bar/x');
        Assert::same((string) Iri::of('foo:bar/baz')->resolve(Iri::of('../x')), 'foo:/x');
    }

    public function removesDotSegmentsOfRootlessAbsolutePaths(): void
    {
        $base = Iri::of('http://a/');

        Assert::same((string) $base->resolve(Iri::of('x:a/b/./c/../d')), 'x:a/b/d');
        Assert::same((string) $base->resolve(Iri::of('x:a/b/c')), 'x:a/b/c');
        Assert::same((string) $base->resolve(Iri::of('x:a')), 'x:a');
        Assert::same((string) $base->resolve(Iri::of('x:../a')), 'x:a');
        Assert::same((string) $base->resolve(Iri::of('x:./a/b')), 'x:a/b');
        Assert::same((string) $base->resolve(Iri::of('x:a/..')), 'x:/');
        Assert::same((string) $base->resolve(Iri::of('x:a/../b')), 'x:/b');
    }

    public function dropsTheSchemeOfAReferenceOnlyWhenAbsent(): void
    {
        $relative = Iri::of('//host/p');

        Assert::null($relative->scheme);
        Assert::same((string) $relative, '//host/p');
        Assert::same((string) Iri::of('s:/p'), 's:/p');
        Assert::same((string) Iri::of('s://h/p'), 's://h/p');
        Assert::null(Iri::of('/p')->scheme);
        Assert::same(Iri::of('s:/p')->scheme, 's');
    }

    public function parsesWithoutPhpDiagnostics(): void
    {
        $parsed = Strict::run(static fn(): array => [
            Iri::of('http://[::1]/x'),
            Iri::of('http://[::1]:90/x'),
            Iri::of('//host/p'),
            Iri::of('/p'),
            Iri::of('s:p'),
            Iri::of(''),
        ]);

        Assert::same(array_map(static fn(Iri $iri): string => (string) $iri, $parsed), [
            'http://[::1]/x',
            'http://[::1]:90/x',
            '//host/p',
            '/p',
            's:p',
            '',
        ]);
    }

    public function splitsPortsAroundIpv6Literals(): void
    {
        $withPort = Iri::of('http://[::1]:8080/x');
        $withoutPort = Iri::of('http://[::1]/x');
        $emptyPort = Iri::of('http://[::1]:/x');
        $unclosed = Iri::of('http://[::1/x');
        $plain = Iri::of('http://h:81/x');

        Assert::same($withPort->host, '[::1]');
        Assert::same($withPort->port, '8080');
        Assert::same($withoutPort->host, '[::1]');
        Assert::null($withoutPort->port);
        Assert::same($emptyPort->host, '[::1]');
        Assert::same($emptyPort->port, '');
        Assert::same($unclosed->host, '[::1');
        Assert::null($unclosed->port);
        Assert::same($plain->host, 'h');
        Assert::same($plain->port, '81');
    }
}
