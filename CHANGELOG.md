# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 — 2026-10-23

- JSON-LD 1.1 expansion, flattening, node API, RDF conversion (`toRdf`, `fromRdf`) and N-Quads.
- W3C JSON-LD API test suite: expand 368/368, flatten 54/55 (one `compactArrays` case skipped), toRdf 447/448 (one empty-object JSON literal case skipped), fromRdf 53/53. Compaction, framing and the 1.0 processing mode are not implemented.
- Context loading only through an injected `ContextLoaderInterface`; the default `InlineContextLoader` refuses URLs, `PsrContextLoader` takes a URL policy closure and a body size limit.
- Checked on 14 JSON-LD blocks collected from real pages (Wikipedia, YouTube, BBC, The New York Times, IMDb, Kommersant, Championat and others): expansion and flattening give the same RDF, N-Quads and `fromRdf` round-trip.
- Mutation score 98.3% (`minMsi` 92).
