<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\DefinitionScope;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\DocumentExpansion;
use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Exception\InvalidContextException;
use Rasuvaeff\JsonLd\Exception\InvalidNQuadsException;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpansionRun;
use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Loader\InlineContextLoader;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Rasuvaeff\JsonLd\Loader\MapContextLoader;
use Rasuvaeff\JsonLd\ProcessingOptions;
use Rasuvaeff\JsonLd\Rdf\BlankNode;
use Rasuvaeff\JsonLd\Rdf\Dataset;
use Rasuvaeff\JsonLd\Rdf\IdentifierIssuer;
use Rasuvaeff\JsonLd\Rdf\JsonCanonicalizer;
use Rasuvaeff\JsonLd\Rdf\Literal;
use Rasuvaeff\JsonLd\Rdf\NQuads;
use Rasuvaeff\JsonLd\Rdf\NQuadsWriter;
use Rasuvaeff\JsonLd\Rdf\Quad;
use Rasuvaeff\JsonLd\Rdf\Vocabulary;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(JsonLdException::class)]
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
#[Covers(DocumentExpansion::class)]
#[Covers(Expander::class)]
#[Covers(Dataset::class)]
#[Covers(Quad::class)]
#[Covers(Literal::class)]
#[Covers(BlankNode::class)]
#[Covers(Vocabulary::class)]
#[Covers(NQuads::class)]
#[Covers(NQuadsWriter::class)]
#[Covers(JsonCanonicalizer::class)]
#[Covers(IdentifierIssuer::class)]
final class ErrorCodeTest
{
    public function theMessageDefaultsToTheCode(): void
    {
        $exception = new InvalidContextException(ErrorCode::InvalidVocabMapping);

        Assert::same($exception->errorCode(), ErrorCode::InvalidVocabMapping);
        Assert::same($exception->getMessage(), 'invalid vocab mapping');
        Assert::same($exception->getCode(), 0);
        Assert::null($exception->getPrevious());
    }

    public function aMessageAndAPreviousExceptionAreKept(): void
    {
        $previous = new \RuntimeException('down');
        $exception = new DocumentLoadingException(ErrorCode::LoadingDocumentFailed, 'cannot fetch', $previous);

        Assert::same($exception->errorCode(), ErrorCode::LoadingDocumentFailed);
        Assert::same($exception->getMessage(), 'cannot fetch');
        Assert::same($exception->getPrevious(), $previous);
    }

    public function processingErrorsCarryTheW3cCode(): void
    {
        $code = null;

        try {
            JsonLd::expand(['@context' => ['@vocab' => true]]);
        } catch (JsonLdException $exception) {
            $code = $exception->errorCode();
        }

        Assert::same($code, ErrorCode::InvalidVocabMapping);
    }

    public function nquadsErrorsHaveTheirOwnCode(): void
    {
        $caught = null;

        try {
            JsonLd::parseNQuads('<http://a> <http://b> .');
        } catch (InvalidNQuadsException $exception) {
            $caught = $exception;
        }

        Assert::same($caught?->errorCode(), ErrorCode::InvalidNQuads);
        Assert::same($caught?->getMessage(), 'Incomplete statement at line 1');
    }
}
