<?php

declare(strict_types=1);

namespace Risan\Sentiment\Internal;

/**
 * The tokens of one text with everything the engine looks up for each of them, by position.
 *
 * A token that acts as a modifier (booster, dampener) has a null lexicon entry, so it can
 * modify a neighbouring sentiment word; a sentiment word has null boosters.
 *
 * @internal
 */
final readonly class Sentence
{
    /**
     * @param array<int, string> $lower lower-cased tokens
     * @param array<int, bool> $isUpper whether the original token is ALL CAPS
     * @param array<int, float|null> $lexicon valence of the token as a sentiment word
     * @param array<int, float|null> $booster scalar of the token as a modifier of the word after it
     * @param array<int, float|null> $postBooster scalar of the token as a modifier of the word before it
     * @param bool $isCapsDifferential some but not all tokens are ALL CAPS
     */
    public function __construct(
        public array $lower,
        public array $isUpper,
        public array $lexicon,
        public array $booster,
        public array $postBooster,
        public bool $isCapsDifferential,
    ) {}
}
