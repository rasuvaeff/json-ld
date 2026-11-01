<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Exception\InvalidContextException;
use Rasuvaeff\JsonLd\Exception\InvalidNQuadsException;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\JsonLd;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(JsonLdException::class)]
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
