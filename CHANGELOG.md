# Changelog

## 2.0.0 - Unreleased

A complete rewrite. The API is not compatible with 1.x.

### Added

- VADER-based scoring for English and Indonesian, with negation, intensifiers, contrast words,
  ALL CAPS, punctuation, emoticon and emoji handling.
- `Sentiment::analyze()` for one-liners and an immutable `Analyzer` with `withWords()`,
  `withoutWords()` and `withThreshold()`.
- Typed results: `Result`, `Label` and `Language`.
- A documentation site at <https://sentiment-analysis.risanb.com>.

### Changed

- Requires PHP 8.3 or newer and `ext-mbstring`.
- The namespace is `Risan\Sentiment` (was `SentimentAnalysis`).
- Lexicons are compiled PHP arrays that OPcache shares between processes.
- Tests run on Pest; formatting uses Laravel Pint (PER style); static analysis uses PHPStan at
  level max; GitHub Actions replaces Travis CI, Scrutinizer and StyleCI.

### Removed

- The 1.x classes and interfaces (`Analyzer::withDefaultConfig()`, `Dictionary`, `Tokenizer`,
  `TokenValidator`, the `Contracts` namespace) and the naive word lists.
