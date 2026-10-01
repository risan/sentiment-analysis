<?php

declare(strict_types=1);

namespace Risan\Sentiment;

use InvalidArgumentException;
use Risan\Sentiment\Internal\Engine;
use Risan\Sentiment\Internal\RuleBook;

/**
 * Immutable: every with*() method returns a modified copy.
 */
final class Analyzer
{
    private const float MAX_VALENCE = 4.0;

    private Language $language;

    private float $threshold;

    private Engine $engine;

    public function __construct(Language|string $language = Language::English, float $threshold = 0.05)
    {
        self::assertThreshold($threshold);

        $this->language = $language instanceof Language ? $language : Language::from($language);
        $this->threshold = $threshold;
        $this->engine = new Engine(RuleBook::for($this->language));
    }

    public function analyze(string $text): Result
    {
        $scores = $this->engine->polarity($text);
        $compound = self::round($scores['compound'], 4);

        return new Result(
            label: $this->labelFor($compound),
            compound: $compound,
            positive: self::round($scores['positive'], 3),
            negative: self::round($scores['negative'], 3),
            neutral: self::round($scores['neutral'], 3),
        );
    }

    /**
     * Adds words to the lexicon or overrides their valence, on VADER's -4..4 scale.
     *
     * @param array<array-key, float|int> $words word => valence; words are lower-cased
     *
     * @throws InvalidArgumentException for an empty word, a word with whitespace or a valence outside -4..4
     */
    public function withWords(array $words): static
    {
        $lexicon = $this->engine->rules->lexicon;

        foreach ($words as $key => $valence) {
            $lexicon[self::normalizeWord((string) $key)] = self::assertValence($valence, (string) $key);
        }

        return $this->withLexicon($lexicon);
    }

    /**
     * @param list<string> $words
     */
    public function withoutWords(array $words): static
    {
        $lexicon = $this->engine->rules->lexicon;

        foreach ($words as $word) {
            unset($lexicon[mb_strtolower($word, 'UTF-8')]);
        }

        return $this->withLexicon($lexicon);
    }

    /**
     * @throws InvalidArgumentException when the threshold is outside [0, 1)
     */
    public function withThreshold(float $threshold): static
    {
        self::assertThreshold($threshold);

        $copy = clone $this;
        $copy->threshold = $threshold;

        return $copy;
    }

    public function language(): Language
    {
        return $this->language;
    }

    /**
     * @param array<array-key, float> $lexicon
     */
    private function withLexicon(array $lexicon): static
    {
        $copy = clone $this;
        $copy->engine = new Engine($this->engine->rules->withLexicon($lexicon));

        return $copy;
    }

    private function labelFor(float $compound): Label
    {
        if ($compound > 0.0 && $compound >= $this->threshold) {
            return Label::Positive;
        }

        if ($compound < 0.0 && $compound <= -$this->threshold) {
            return Label::Negative;
        }

        return Label::Neutral;
    }

    /**
     * Rounds like Python's round(), which round() in PHP does not: sprintf uses
     * correctly rounded decimal conversion for both.
     */
    private static function round(float $value, int $decimals): float
    {
        $rounded = (float) sprintf("%.{$decimals}F", $value);

        return $rounded === 0.0 ? 0.0 : $rounded;
    }

    private static function assertThreshold(float $threshold): void
    {
        if (!($threshold >= 0.0 && $threshold < 1.0)) {
            throw new InvalidArgumentException('The threshold must be at least 0 and below 1.');
        }
    }

    private static function normalizeWord(string $word): string
    {
        $word = mb_strtolower($word, 'UTF-8');

        if ($word === '' || preg_match('/' . Engine::WHITESPACE . '/u', $word) !== 0) {
            throw new InvalidArgumentException('A lexicon word must be a non-empty string without whitespace.');
        }

        return $word;
    }

    private static function assertValence(mixed $valence, string $word): float
    {
        if ((!is_int($valence) && !is_float($valence)) || !is_finite($valence) || abs($valence) > self::MAX_VALENCE) {
            throw new InvalidArgumentException("The valence of \"{$word}\" must be a number from -4 to 4.");
        }

        return (float) $valence;
    }
}
