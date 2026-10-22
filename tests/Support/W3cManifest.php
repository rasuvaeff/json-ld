<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Rasuvaeff\JsonLd\Json;

/**
 * Reads a W3C test-suite manifest and yields named test cases with their
 * input, expectation and options already resolved.
 */
final readonly class W3cManifest
{
    public function __construct(private string $fixturesDir = __DIR__ . '/../fixtures/w3c') {}

    /**
     * @param string $suite expand|flatten|toRdf|fromRdf
     *
     * @return iterable<string, array{
     *     input: mixed,
     *     expected: mixed,
     *     expectedError: ?string,
     *     base: ?string,
     *     expandContext: mixed,
     *     processingMode: ?string,
     *     option: array<string, mixed>,
     * }>
     */
    public function cases(string $suite): iterable
    {
        $manifest = Json::decode((string) file_get_contents($this->fixturesDir . '/' . $suite . '-manifest.jsonld'));

        foreach ($manifest['sequence'] as $case) {
            $name = $case['name'] ?? $case['@id'];
            $option = $case['option'] ?? [];
            $processingMode = $option['processingMode'] ?? null;

            if ($processingMode === 'json-ld-1.0') {
                continue;
            }

            $types = is_array($case['@type'] ?? null) ? $case['@type'] : [];

            $negative = in_array('jld:NegativeEvaluationTest', $types, true);
            $syntaxOnly = in_array('jld:PositiveSyntaxTest', $types, true);

            $input = $this->load($case['input'] ?? '');
            $expected = null;
            $expectedError = null;

            if ($negative) {
                $expectedError = isset($case['expectErrorCode']) ? (string) $case['expectErrorCode'] : null;
            } elseif (isset($case['expect'])) {
                $expected = $this->load((string) $case['expect']);
            }

            $inputPath = $case['input'] ?? null;

            yield (string) $name => [
                'input' => $input,
                'expected' => $expected,
                'expectedError' => $expectedError,
                'base' => $this->base($option, is_string($inputPath) ? $inputPath : null),
                'expandContext' => isset($option['expandContext']) ? $this->load($option['expandContext']) : null,
                'processingMode' => $processingMode,
                'syntaxOnly' => $syntaxOnly,
                'option' => $option,
            ];
        }
    }

    private function load(string $path): mixed
    {
        if ($path === '') {
            return null;
        }

        $file = $this->fixturesDir . '/' . $path;

        if (!is_file($file)) {
            throw new \RuntimeException(sprintf('Fixture "%s" not found', $path));
        }

        $contents = (string) file_get_contents($file);

        if (str_ends_with($path, '.nq')) {
            return $contents;
        }

        return Json::decode($contents);
    }

    /**
     * @param array<string, mixed> $option
     */
    private function base(array $option, ?string $inputPath): ?string
    {
        $base = $option['base'] ?? null;

        if (is_string($base)) {
            if (str_starts_with($base, 'http://') || str_starts_with($base, 'https://')) {
                return $base;
            }

            return FixtureContextLoader::BASE_IRI . $base;
        }

        // Without an explicit base option, the document location is the
        // base: suite convention is the full input URL.
        if (is_string($inputPath)) {
            return FixtureContextLoader::BASE_IRI . $inputPath;
        }

        return null;
    }
}
