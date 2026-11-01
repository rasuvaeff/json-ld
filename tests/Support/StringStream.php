<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Psr\Http\Message\StreamInterface;

final class StringStream implements StreamInterface
{
    public int $reads = 0;

    public ?int $lastReadLength = null;

    private int $position = 0;

    /**
     * @param bool $sizeKnown false makes getSize() answer null, like a chunked response
     */
    public function __construct(
        private readonly string $contents,
        private readonly bool $sizeKnown = true,
    ) {}

    #[\Override]
    public function __toString(): string
    {
        return $this->contents;
    }

    #[\Override]
    public function close(): void {}

    #[\Override]
    public function detach()
    {
        return null;
    }

    #[\Override]
    public function getSize(): ?int
    {
        return $this->sizeKnown ? strlen($this->contents) : null;
    }

    #[\Override]
    public function tell(): int
    {
        return $this->position;
    }

    #[\Override]
    public function eof(): bool
    {
        return $this->position >= strlen($this->contents);
    }

    #[\Override]
    public function isSeekable(): bool
    {
        return false;
    }

    #[\Override]
    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new \LogicException('not seekable');
    }

    #[\Override]
    public function rewind(): void
    {
        throw new \LogicException('not seekable');
    }

    #[\Override]
    public function isWritable(): bool
    {
        return false;
    }

    #[\Override]
    public function write($string): int
    {
        throw new \LogicException('not writable');
    }

    #[\Override]
    public function isReadable(): bool
    {
        return true;
    }

    #[\Override]
    public function read($length): string
    {
        ++$this->reads;
        $this->lastReadLength = $length;
        $chunk = substr($this->contents, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    #[\Override]
    public function getContents(): string
    {
        return $this->read(strlen($this->contents));
    }

    #[\Override]
    public function getMetadata($key = null)
    {
        return null;
    }
}
