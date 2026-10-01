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
     * @param array<string, float> $boosters words that scale the valence of a sentiment word after them
     * @param array<string, float> $postBoosters words that scale the valence of a sentiment word before them
     * @param array<string, true> $contrasts words that behave like "but"
     * @param array<string, true> $phrases two-word modifiers, merged into one token before scoring
     * @param array<string, true> $dualRole words that are both sentiment words and modifiers
     * @param array<string, float> $specialCases idioms with a fixed valence (English only)
     * @param list<string> $clitics suffixes to strip when a word is not in the lexicon
     * @param bool $reduplication whether "x-x" and "x2" fall back to "x"
     * @param bool $englishQuirks the English-only rules: "n't", "no", "least", "never so", "without doubt", "kind of"
     */
    public function __construct(
        public array $lexicon,
        public array $emoji,
        public string $emojiPattern,
        public array $negations,
        public array $boosters,
        public array $postBoosters,
        public array $contrasts,
        public array $phrases,
        public array $dualRole,
        public array $specialCases,
        public array $clitics,
        public bool $reduplication,
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
            postBoosters: $this->postBoosters,
            contrasts: $this->contrasts,
            phrases: $this->phrases,
            dualRole: $this->dualRole,
            specialCases: $this->specialCases,
            clitics: $this->clitics,
            reduplication: $this->reduplication,
            englishQuirks: $this->englishQuirks,
        );
    }
}
