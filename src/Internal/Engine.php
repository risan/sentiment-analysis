<?php

declare(strict_types=1);

namespace Risan\Sentiment\Internal;

/**
 * The VADER algorithm (Hutto & Gilbert, 2014), ported from vaderSentiment 3.3.2
 * and driven by the data in Rules. Scores are not rounded here.
 *
 * Two deliberate deviations from the reference, both documented in the README:
 * "but" scales sentiments by position instead of by list.index(value), and
 * U+FE0F (emoji variation selector) is dropped before emoji conversion.
 *
 * @internal
 */
final class Engine
{
    /** Exactly the characters Python's str.isspace() accepts; PCRE's \s differs. */
    public const string WHITESPACE = '[\t\n\x0B\f\r\x1C-\x1F \x{85}\x{A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]';

    private const string TOKEN_SEPARATOR = '/' . self::WHITESPACE . '+/u';

    /** Python's string.punctuation. */
    private const string PUNCTUATION = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';

    private const string VARIATION_SELECTOR = "\u{FE0F}";

    private const float CAPS_INCREMENT = 0.733;

    private const float NEGATION_SCALAR = -0.74;

    private const float EXCLAMATION_INCREMENT = 0.292;

    private const float QUESTION_INCREMENT = 0.18;

    private const float QUESTION_CAP = 0.96;

    private const float NORMALIZATION_ALPHA = 15.0;

    public function __construct(public readonly Rules $rules) {}

    /**
     * @return array{compound: float, positive: float, negative: float, neutral: float}
     */
    public function polarity(string $text): array
    {
        $text = self::scrub($text);
        $ascii = preg_match('/[^\x00-\x7F]/', $text) !== 1;

        if (!$ascii) {
            $text = $this->describeEmoji(str_replace(self::VARIATION_SELECTOR, '', $text));
        }

        $tokens = preg_split(self::TOKEN_SEPARATOR, $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($tokens);

        if ($count === 0) {
            return ['compound' => 0.0, 'positive' => 0.0, 'negative' => 0.0, 'neutral' => 0.0];
        }

        $rules = $this->rules;
        $lower = [];
        $isUpper = [];
        $lexicon = [];
        $booster = [];
        $contrastIndex = null;
        $upperCount = 0;

        foreach ($tokens as $i => $token) {
            $stripped = trim($token, self::PUNCTUATION);
            $length = $ascii ? strlen($stripped) : mb_strlen($stripped, 'UTF-8');
            $word = $length <= 2 ? $token : $stripped;
            $lowered = $ascii ? strtolower($word) : mb_strtolower($word, 'UTF-8');
            $upper = $ascii ? $lowered !== $word && strtoupper($word) === $word : self::isUpper($word);

            $lower[$i] = $lowered;
            $isUpper[$i] = $upper;
            $lexicon[$i] = $rules->lexicon[$lowered] ?? null;
            $booster[$i] = $rules->boosters[$lowered] ?? null;

            if ($upper) {
                $upperCount++;
            }

            if ($contrastIndex === null && isset($rules->contrasts[$lowered])) {
                $contrastIndex = $i;
            }
        }

        $isCapsDifferential = $upperCount > 0 && $upperCount < $count;
        $sentiments = [];

        for ($i = 0; $i < $count; $i++) {
            $sentiments[] = $this->isSkipped($lower, $booster, $i) || $lexicon[$i] === null
                ? 0.0
                : $this->wordValence($lower, $isUpper, $lexicon, $booster, $isCapsDifferential, $i);
        }

        if ($contrastIndex !== null) {
            foreach ($sentiments as $position => $sentiment) {
                if ($position < $contrastIndex) {
                    $sentiments[$position] = $sentiment * 0.5;
                } elseif ($position > $contrastIndex) {
                    $sentiments[$position] = $sentiment * 1.5;
                }
            }
        }

        return $this->combine($sentiments, $text);
    }

    private static function scrub(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        // The default substitute, "?", would add false question mark emphasis.
        $previous = mb_substitute_character();
        mb_substitute_character('none');

        try {
            return mb_scrub($text, 'UTF-8');
        } finally {
            mb_substitute_character($previous);
        }
    }

    private static function isUpper(string $word): bool
    {
        return preg_match('/\p{Lu}/u', $word) === 1 && preg_match('/[\p{Ll}\p{Lt}]/u', $word) === 0;
    }

    /**
     * Replaces each emoji with its description, like the reference: a space goes
     * before the description unless the text starts there or a literal space precedes.
     */
    private function describeEmoji(string $text): string
    {
        return preg_replace_callback(
            $this->rules->emojiPattern,
            fn(array $match): string => $this->emojiDescription($match, $text),
            $text,
            -1,
            $count,
            PREG_OFFSET_CAPTURE,
        ) ?? $text;
    }

    /**
     * @param array<array-key, mixed> $match
     */
    private function emojiDescription(array $match, string $text): string
    {
        // PREG_OFFSET_CAPTURE makes each match a [text, offset] pair.
        /** @var array<int, array{string, int}> $match */
        [$emoji, $offset] = $match[0];
        $needsSpace = $offset > 0 && $text[$offset - 1] !== ' ';

        return ($needsSpace ? ' ' : '') . $this->rules->emoji[$emoji];
    }

    /**
     * Boosters and "kind of" are modifiers, not sentiment words.
     *
     * @param array<int, string> $lower
     * @param array<int, float|null> $booster
     */
    private function isSkipped(array $lower, array $booster, int $i): bool
    {
        return $booster[$i] !== null
            || ($this->rules->englishQuirks && $lower[$i] === 'kind' && ($lower[$i + 1] ?? null) === 'of');
    }

    /**
     * @param array<int, string> $lower
     * @param array<int, bool> $isUpper
     * @param array<int, float|null> $lexicon
     * @param array<int, float|null> $booster
     */
    private function wordValence(array $lower, array $isUpper, array $lexicon, array $booster, bool $isCapsDifferential, int $i): float
    {
        $quirks = $this->rules->englishQuirks;
        $base = (float) $lexicon[$i];
        $valence = $base;

        if ($quirks) {
            if ($lower[$i] === 'no' && ($lexicon[$i + 1] ?? null) !== null) {
                $valence = 0.0;
            }

            if (
                ($i > 0 && $lower[$i - 1] === 'no')
                || ($i > 1 && $lower[$i - 2] === 'no')
                || ($i > 2 && $lower[$i - 3] === 'no' && ($lower[$i - 1] === 'or' || $lower[$i - 1] === 'nor'))
            ) {
                $valence = $base * self::NEGATION_SCALAR;
            }
        }

        if ($isUpper[$i] && $isCapsDifferential) {
            $valence += $valence > 0 ? self::CAPS_INCREMENT : -self::CAPS_INCREMENT;
        }

        for ($start = 0; $start < 3; $start++) {
            $neighbour = $i - $start - 1;

            if ($i <= $start || $lexicon[$neighbour] !== null) {
                continue;
            }

            $scalar = $this->boosterScalar($booster[$neighbour], $isUpper[$neighbour], $isCapsDifferential, $valence);

            if ($start === 1) {
                $scalar *= 0.95;
            } elseif ($start === 2) {
                $scalar *= 0.9;
            }

            $valence = $this->negationCheck($valence + $scalar, $lower, $start, $i);

            if ($start === 2 && $quirks) {
                $valence = $this->specialIdioms($valence, $lower, $i);
            }
        }

        return $quirks ? $this->leastCheck($valence, $lower, $lexicon, $i) : $valence;
    }

    private function boosterScalar(?float $boost, bool $isUpper, bool $isCapsDifferential, float $valence): float
    {
        if ($boost === null) {
            return 0.0;
        }

        $scalar = $valence < 0 ? -$boost : $boost;

        if ($isUpper && $isCapsDifferential) {
            $scalar += $valence > 0 ? self::CAPS_INCREMENT : -self::CAPS_INCREMENT;
        }

        return $scalar;
    }

    /**
     * @param array<int, string> $lower
     */
    private function negationCheck(float $valence, array $lower, int $start, int $i): float
    {
        if ($this->rules->englishQuirks && $start > 0) {
            $multiplier = $this->negationQuirk($lower, $start, $i);

            if ($multiplier !== null) {
                return $valence * $multiplier;
            }
        }

        return $this->isNegation($lower[$i - $start - 1]) ? $valence * self::NEGATION_SCALAR : $valence;
    }

    /**
     * "never so good" and "never this good" amplify, "without doubt" cancels the negation.
     * The start === 2 condition keeps Python's precedence: (A and (B or C)) or (D or E).
     *
     * @param array<int, string> $lower
     */
    private function negationQuirk(array $lower, int $start, int $i): ?float
    {
        if ($start === 1) {
            if ($lower[$i - 2] === 'never' && ($lower[$i - 1] === 'so' || $lower[$i - 1] === 'this')) {
                return 1.25;
            }

            return $lower[$i - 2] === 'without' && $lower[$i - 1] === 'doubt' ? 1.0 : null;
        }

        if (
            ($lower[$i - 3] === 'never' && ($lower[$i - 2] === 'so' || $lower[$i - 2] === 'this'))
            || ($lower[$i - 1] === 'so' || $lower[$i - 1] === 'this')
        ) {
            return 1.25;
        }

        return $lower[$i - 3] === 'without' && ($lower[$i - 2] === 'doubt' || $lower[$i - 1] === 'doubt') ? 1.0 : null;
    }

    private function isNegation(string $word): bool
    {
        return isset($this->rules->negations[$word])
            || ($this->rules->englishQuirks && str_contains($word, "n't"));
    }

    /**
     * @param array<int, string> $lower
     */
    private function specialIdioms(float $valence, array $lower, int $i): float
    {
        $cases = $this->rules->specialCases;
        $oneZero = $lower[$i - 1] . ' ' . $lower[$i];
        $twoOne = $lower[$i - 2] . ' ' . $lower[$i - 1];
        $twoOneZero = $lower[$i - 2] . ' ' . $oneZero;
        $threeTwo = $lower[$i - 3] . ' ' . $lower[$i - 2];
        $threeTwoOne = $lower[$i - 3] . ' ' . $twoOne;

        foreach ([$oneZero, $twoOneZero, $twoOne, $threeTwoOne, $threeTwo] as $sequence) {
            if (isset($cases[$sequence])) {
                $valence = $cases[$sequence];

                break;
            }
        }

        if (isset($lower[$i + 1]) && isset($cases[$lower[$i] . ' ' . $lower[$i + 1]])) {
            $valence = $cases[$lower[$i] . ' ' . $lower[$i + 1]];
        }

        if (isset($lower[$i + 2]) && isset($cases[$lower[$i] . ' ' . $lower[$i + 1] . ' ' . $lower[$i + 2]])) {
            $valence = $cases[$lower[$i] . ' ' . $lower[$i + 1] . ' ' . $lower[$i + 2]];
        }

        foreach ([$threeTwoOne, $threeTwo, $twoOne] as $phrase) {
            if (isset($this->rules->boosters[$phrase])) {
                $valence += $this->rules->boosters[$phrase];
            }
        }

        return $valence;
    }

    /**
     * "least" negates unless it is part of "at least" or "very least".
     *
     * @param array<int, string> $lower
     * @param array<int, float|null> $lexicon
     */
    private function leastCheck(float $valence, array $lower, array $lexicon, int $i): float
    {
        if ($i > 1 && $lexicon[$i - 1] === null && $lower[$i - 1] === 'least') {
            return $lower[$i - 2] !== 'at' && $lower[$i - 2] !== 'very' ? $valence * self::NEGATION_SCALAR : $valence;
        }

        if ($i > 0 && $lexicon[$i - 1] === null && $lower[$i - 1] === 'least') {
            return $valence * self::NEGATION_SCALAR;
        }

        return $valence;
    }

    /**
     * @param list<float> $sentiments
     *
     * @return array{compound: float, positive: float, negative: float, neutral: float}
     */
    private function combine(array $sentiments, string $text): array
    {
        $sum = 0.0;
        $positiveSum = 0.0;
        $negativeSum = 0.0;
        $neutralCount = 0;

        foreach ($sentiments as $sentiment) {
            $sum += $sentiment;

            if ($sentiment > 0) {
                $positiveSum += $sentiment + 1;
            } elseif ($sentiment < 0) {
                $negativeSum += $sentiment - 1;
            } else {
                $neutralCount++;
            }
        }

        $emphasis = self::punctuationEmphasis($text);

        if ($sum > 0) {
            $sum += $emphasis;
        } elseif ($sum < 0) {
            $sum -= $emphasis;
        }

        if ($positiveSum > abs($negativeSum)) {
            $positiveSum += $emphasis;
        } elseif ($positiveSum < abs($negativeSum)) {
            $negativeSum -= $emphasis;
        }

        $total = $positiveSum + abs($negativeSum) + $neutralCount;

        return [
            'compound' => self::normalize($sum),
            'positive' => abs($positiveSum / $total),
            'negative' => abs($negativeSum / $total),
            'neutral' => abs($neutralCount / $total),
        ];
    }

    private static function punctuationEmphasis(string $text): float
    {
        $exclamations = min(substr_count($text, '!'), 4) * self::EXCLAMATION_INCREMENT;
        $questions = substr_count($text, '?');

        if ($questions < 2) {
            return $exclamations;
        }

        return $exclamations + ($questions <= 3 ? $questions * self::QUESTION_INCREMENT : self::QUESTION_CAP);
    }

    private static function normalize(float $score): float
    {
        return max(-1.0, min(1.0, $score / sqrt($score * $score + self::NORMALIZATION_ALPHA)));
    }
}
