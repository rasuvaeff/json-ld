<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

/**
 * An immutable IRI reference with RFC 3986 parsing and relative reference
 * resolution (sections 3, 5.2 and 5.3 of the RFC).
 *
 * Components are kept verbatim: no case folding, no percent-encoding
 * normalization. IRIs are compared as strings (RFC 3987 section 5.3.1),
 * which is what the JSON-LD algorithms require.
 *
 * @api
 */
final readonly class Iri
{
    /**
     * IRI reference grammar for scheme, authority and path, RFC 3986
     * appendix B (query and fragment are split by the caller).
     */
    private const string PATTERN = '~^(?:([^:/?#]*):)?(?:\/\/([^/?#]*))?([^?#]*)$~';

    /**
     * @param string|null $scheme scheme without the trailing colon, null when absent
     * @param string|null $userInfo userinfo without the trailing @, null when absent
     * @param string|null $host host, null when the whole authority is absent; an empty string means an empty authority (`http:///path`)
     * @param string|null $port port without the leading colon, null when absent
     * @param string $path path, possibly empty
     * @param string|null $query query without the leading question mark, null when absent
     * @param string|null $fragment fragment without the leading hash, null when absent
     */
    private function __construct(
        public ?string $scheme,
        public ?string $userInfo,
        public ?string $host,
        public ?string $port,
        public string $path,
        public ?string $query,
        public ?string $fragment,
    ) {}

    /**
     * Parses an IRI reference. An empty string yields an empty reference.
     */
    public static function of(string $iri): self
    {
        // Split the fragment first, then the query: '#' can only start the
        // fragment and '?' can only start the query, so the first occurrence
        // of each is the boundary. An empty fragment/query ("a?", "a#") stays
        // present-but-empty, unlike an absent one.
        $beforeFragment = $iri;
        $fragment = null;

        $hash = strpos($iri, '#');

        if ($hash !== false) {
            $fragment = substr($iri, $hash + 1);
            $beforeFragment = substr($iri, 0, $hash);
        }

        $beforeQuery = $beforeFragment;
        $query = null;

        $question = strpos($beforeFragment, '?');

        if ($question !== false) {
            $query = substr($beforeFragment, $question + 1);
            $beforeQuery = substr($beforeFragment, 0, $question);
        }

        if (preg_match(self::PATTERN, $beforeQuery, $matches) !== 1) {
            throw new \InvalidArgumentException('Invalid IRI reference');
        }

        [, $scheme, $authority, $path] = $matches;

        $userInfo = null;
        $host = null;
        $port = null;

        if ($authority !== '') {
            $hostPart = $authority;

            $at = strpos($hostPart, '@');

            if ($at !== false) {
                $userInfo = substr($hostPart, 0, $at);
                $hostPart = substr($hostPart, $at + 1);
            }

            // The port starts at the first colon that is not inside an IPv6 literal.
            $colon = self::portColon($hostPart);

            if ($colon !== null) {
                $port = substr($hostPart, $colon + 1);
                $hostPart = substr($hostPart, 0, $colon);
            }

            $host = $hostPart;
        }

        return new self(
            scheme: $scheme !== '' ? $scheme : null,
            userInfo: $userInfo,
            host: $host,
            port: $port,
            path: $path,
            query: $query,
            fragment: $fragment,
        );
    }

    public function __toString(): string
    {
        $result = '';

        if ($this->scheme !== null) {
            $result .= $this->scheme . ':';
        }

        if ($this->host !== null) {
            $result .= '//';

            if ($this->userInfo !== null) {
                $result .= $this->userInfo . '@';
            }

            $result .= $this->host;

            if ($this->port !== null) {
                $result .= ':' . $this->port;
            }
        }

        $result .= $this->path;

        if ($this->query !== null) {
            $result .= '?' . $this->query;
        }

        if ($this->fragment !== null) {
            $result .= '#' . $this->fragment;
        }

        return $result;
    }

    /**
     * An absolute IRI has a scheme (RFC 3986 section 4.3); the authority is
     * optional (`urn:isbn:…` is absolute).
     */
    public function isAbsolute(): bool
    {
        return $this->scheme !== null;
    }

    /**
     * Resolves this reference against the given base, RFC 3986 section 5.2.
     *
     * @param self $reference the reference to resolve against this IRI used as the base
     */
    public function resolve(self $reference): self
    {
        if ($reference->scheme !== null) {
            return $reference->withPath(self::removeDotSegments($reference->path));
        }

        if ($reference->host !== null) {
            return new self(
                scheme: $this->scheme,
                userInfo: $reference->userInfo,
                host: $reference->host,
                port: $reference->port,
                path: self::removeDotSegments($reference->path),
                query: $reference->query,
                fragment: $reference->fragment,
            );
        }

        if ($reference->path === '') {
            return new self(
                scheme: $this->scheme,
                userInfo: $this->userInfo,
                host: $this->host,
                port: $this->port,
                path: $this->path,
                query: $reference->query ?? $this->query,
                fragment: $reference->fragment,
            );
        }

        $path = str_starts_with($reference->path, '/')
            ? $reference->path
            : $this->mergePath($reference->path);

        return new self(
            scheme: $this->scheme,
            userInfo: $this->userInfo,
            host: $this->host,
            port: $this->port,
            path: self::removeDotSegments($path),
            query: $reference->query,
            fragment: $reference->fragment,
        );
    }

    /**
     * RFC 3986 section 5.2.3.
     */
    private function mergePath(string $referencePath): string
    {
        if ($this->host !== null && $this->path === '') {
            return '/' . $referencePath;
        }

        $slash = strrpos($this->path, '/');

        if ($slash === false) {
            return $referencePath;
        }

        return substr($this->path, 0, $slash + 1) . $referencePath;
    }

    /**
     * RFC 3986 section 5.2.4.
     */
    private static function removeDotSegments(string $input): string
    {
        $output = '';

        while ($input !== '') {
            if (str_starts_with($input, '../')) {
                $input = substr($input, 3);
            } elseif (str_starts_with($input, './')) {
                $input = substr($input, 2);
            } elseif (str_starts_with($input, '/./')) {
                $input = substr($input, 2);
            } elseif ($input === '/.') {
                $input = '/';
            } elseif (str_starts_with($input, '/../')) {
                $input = '/' . substr($input, 4);
                $output = self::removeLastSegment($output);
            } elseif ($input === '/..') {
                $input = '/';
                $output = self::removeLastSegment($output);
            } elseif ($input === '.' || $input === '..') {
                $input = '';
            } elseif (str_starts_with($input, '/')) {
                $next = strpos($input, '/', 1);
                $output .= $next === false ? $input : substr($input, 0, $next);
                $input = $next === false ? '' : substr($input, $next);
            } else {
                $next = strpos($input, '/');
                $output .= $next === false ? $input : substr($input, 0, $next);
                $input = $next === false ? '' : substr($input, $next);
            }
        }

        return $output;
    }

    private static function removeLastSegment(string $output): string
    {
        $slash = strrpos($output, '/');

        return $slash === false ? '' : substr($output, 0, $slash);
    }

    private function withPath(string $path): self
    {
        return new self(
            scheme: $this->scheme,
            userInfo: $this->userInfo,
            host: $this->host,
            port: $this->port,
            path: $path,
            query: $this->query,
            fragment: $this->fragment,
        );
    }

    /**
     * Position of the port colon in an authority host part, ignoring colons
     * inside an IPv6 literal.
     *
     * @return int<0, max>|null
     */
    private static function portColon(string $host): ?int
    {
        if (str_starts_with($host, '[')) {
            $closing = strpos($host, ']');

            if ($closing !== false && $closing + 1 < strlen($host) && $host[$closing + 1] === ':') {
                return $closing + 1;
            }

            return null;
        }

        $colon = strpos($host, ':');

        return $colon === false ? null : $colon;
    }
}
