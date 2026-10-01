<?php

declare(strict_types=1);

namespace Risan\Sentiment\Internal;

/**
 * Everything the engine needs to score one language. Plain data, no behaviour.
 *
 * @internal
 */
final readonly class Rules
{
    /**
     * @param array<array-key, float> $lexicon word valences on VADER's -4..4 scale
     * @param array<string, string> $emoji single code point emoji and their descriptions
     * @param string $emojiPattern regular expression matching any key of $emoji
     * @param array<string, true> $negations
     * @param array<string, float> $boosters words that scale the valence of a following lexicon word
     * @param array<string, true> $contrasts words that behave like "but"
     * @param array<string, float> $specialCases idioms with a fixed valence (English only)
     * @param bool $englishQuirks the English-only rules: "n't", "no", "least", "never so", "without doubt", "kind of"
     */
    public function __construct(
        public array $lexicon,
        public array $emoji,
        public string $emojiPattern,
        public array $negations,
        public array $boosters,
        public array $contrasts,
        public array $specialCases,
        public bool $englishQuirks,
    ) {}

    /**
     * @param array<array-key, float> $lexicon
     */
    public function withLexicon(array $lexicon): self
    {
        return new self(
            lexicon: $lexicon,
            emoji: $this->emoji,
            emojiPattern: $this->emojiPattern,
            negations: $this->negations,
            boosters: $this->boosters,
            contrasts: $this->contrasts,
            specialCases: $this->specialCases,
            englishQuirks: $this->englishQuirks,
        );
    }
}
