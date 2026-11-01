<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Exception;

use Exception;
use Throwable;

/**
 * Base class of every exception thrown by the JSON-LD processor.
 *
 * {@see self::errorCode()} tells failures apart; the message is meant for
 * people and, unless a more specific one is given, equals the error code.
 *
 * @api
 */
abstract class JsonLdException extends Exception
{
    private readonly ErrorCode $errorCode;

    public function __construct(ErrorCode $errorCode, ?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct($message ?? $errorCode->value, 0, $previous);
        $this->errorCode = $errorCode;
    }

    public function errorCode(): ErrorCode
    {
        return $this->errorCode;
    }
}
