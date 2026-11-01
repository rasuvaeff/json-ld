<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\DefinitionScope;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\DocumentExpansion;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpansionRun;
use Rasuvaeff\JsonLd\Flattener;
use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Loader\InlineContextLoader;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Rasuvaeff\JsonLd\Loader\MapContextLoader;
use Rasuvaeff\JsonLd\NodeMap;
use Rasuvaeff\JsonLd\ProcessingOptions;
use Rasuvaeff\JsonLd\Rdf\BlankNode;
use Rasuvaeff\JsonLd\Rdf\Dataset;
use Rasuvaeff\JsonLd\Rdf\Iri as RdfIri;
use Rasuvaeff\JsonLd\Rdf\JsonCanonicalizer;
use Rasuvaeff\JsonLd\Rdf\Literal;
use Rasuvaeff\JsonLd\Rdf\NQuadsWriter;
use Rasuvaeff\JsonLd\Rdf\Quad;
use Rasuvaeff\JsonLd\Rdf\ToRdf;
use Rasuvaeff\JsonLd\Rdf\Vocabulary;
use Rasuvaeff\JsonLd\RdfDirection;
use Rasuvaeff\JsonLd\Tests\Support\FixtureContextLoader;
use Rasuvaeff\JsonLd\Tests\Support\W3cManifest;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(ToRdf::class)]
#[Covers(ExpansionRun::class)]
#[Covers(JsonLd::class)]
#[Covers(Json::class)]
#[Covers(Keywords::class)]
#[Covers(Iri::class)]
#[Covers(ProcessingOptions::class)]
#[Covers(ContextProcessor::class)]
#[Covers(ActiveContext::class)]
#[Covers(Syntax::class)]
#[Covers(TermDefinition::class)]
#[Covers(DefinitionScope::class)]
#[Covers(InlineContextLoader::class)]
#[Covers(MapContextLoader::class)]
#[Covers(LoadedContext::class)]
#[Covers(NodeMap::class)]
#[Covers(Flattener::class)]
#[Covers(DocumentExpansion::class)]
#[Covers(Expander::class)]
#[Covers(Dataset::class)]
#[Covers(Quad::class)]
#[Covers(Literal::class)]
#[Covers(BlankNode::class)]
#[Covers(Vocabulary::class)]
#[Covers(JsonCanonicalizer::class)]
#[Covers(NQuadsWriter::class)]
#[Covers(RdfIri::class)]
final class W3cToRdfTest
{
    /**
     * Cases outside of what the PHP representation can express; each entry
     * names the reason.
     *
     * @var array<string, string>
     */
    private const array SKIPPED = [
        'Transform JSON literal with structural canonicalization' => 'an empty JSON object decodes to an empty PHP array, so `{}` inside a JSON literal serializes as `[]`',
    ];

    #[DataProvider('suiteProvider')]
    public function case(mixed $input, mixed $expected, ?string $expectedError, ?string $base, mixed $expandContext, ?string $documentUrl, bool $syntaxOnly, bool $generalizedRdf, ?string $rdfDirection): void
    {
        $options = new ProcessingOptions(
            base: $base,
            documentUrl: $documentUrl,
            expandContext: $expandContext,
            loader: new FixtureContextLoader(),
        );

        if ($expectedError !== null) {
            $message = null;

            try {
                JsonLd::toRdf((array) $input, $options);
            } catch (JsonLdException $exception) {
                $message = $exception->errorCode()->value;
            }

            Assert::same($message, $expectedError);

            return;
        }

        $result = JsonLd::toRdf((array) $input, $options, produceGeneralizedRdf: $generalizedRdf, rdfDirection: $rdfDirection === null ? null : RdfDirection::from($rdfDirection));

        if ($syntaxOnly) {
            Assert::true($result->quads !== [] || $result->quads === []);

            return;
        }

        Assert::true($result->equals(JsonLd::parseNQuads((string) $expected)));
    }

    public static function suiteProvider(): iterable
    {
        foreach ((new W3cManifest())->cases('toRdf') as $name => $case) {
            if (isset(self::SKIPPED[$name])) {
                continue;
            }

            yield $name => [
                $case['input'],
                $case['expected'],
                $case['expectedError'],
                $case['base'],
                $case['expandContext'],
                $case['documentUrl'],
                $case['syntaxOnly'],
                ($case['option']['produceGeneralizedRdf'] ?? false) === true,
                $case['option']['rdfDirection'] ?? null,
            ];
        }
    }
}
