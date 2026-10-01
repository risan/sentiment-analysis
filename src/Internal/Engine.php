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

        if ($tokens === []) {
            return ['compound' => 0.0, 'positive' => 0.0, 'negative' => 0.0, 'neutral' => 0.0];
        }

        [$words, $lower] = $this->stripPunctuation($tokens, $ascii);

        if ($this->rules->phrases !== []) {
            [$words, $lower] = $this->mergePhrases($words, $lower);
        }

        $sentence = $this->analyzeTokens($words, $lower, $ascii);
        $sentiments = [];

        for ($i = 0, $count = count($lower); $i < $count; $i++) {
            $sentiments[] = $this->isModifier($sentence, $i) || $sentence->lexicon[$i] === null
                ? 0.0
                : $this->wordValence($sentence, $i);
        }

        return $this->combine($this->weighContrast($sentiments, $lower), $text);
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
     * Strips surrounding punctuation like the reference: a token that would shrink to
     * two characters or fewer is probably an emoticon and stays whole.
     *
     * @param list<string> $tokens
     *
     * @return array{array<int, string>, array<int, string>} the tokens and their lower-case forms
     */
    private function stripPunctuation(array $tokens, bool $ascii): array
    {
        $words = [];
        $lower = [];

        foreach ($tokens as $i => $token) {
            $stripped = trim($token, self::PUNCTUATION);
            $length = $ascii ? strlen($stripped) : mb_strlen($stripped, 'UTF-8');
            $word = $length <= 2 ? $token : $stripped;

            $words[$i] = $word;
            $lower[$i] = $ascii ? strtolower($word) : mb_strtolower($word, 'UTF-8');
        }

        return [$words, $lower];
    }

    /**
     * @param array<int, string> $words
     * @param array<int, string> $lower
     *
     * @return array{array<int, string>, array<int, string>}
     */
    private function mergePhrases(array $words, array $lower): array
    {
        $mergedWords = [];
        $mergedLower = [];

        for ($i = 0, $count = count($lower); $i < $count; $i++) {
            if ($i + 1 < $count && isset($this->rules->phrases[$lower[$i] . ' ' . $lower[$i + 1]])) {
                $mergedWords[] = $words[$i] . ' ' . $words[$i + 1];
                $mergedLower[] = $lower[$i] . ' ' . $lower[$i + 1];
                $i++;

                continue;
            }

            $mergedWords[] = $words[$i];
            $mergedLower[] = $lower[$i];
        }

        return [$mergedWords, $mergedLower];
    }

    /**
     * @param array<int, string> $words
     * @param array<int, string> $lower
     */
    private function analyzeTokens(array $words, array $lower, bool $ascii): Sentence
    {
        $rules = $this->rules;
        $hasFallback = $rules->clitics !== [] || $rules->reduplication;
        $isUpper = [];
        $lexicon = [];
        $booster = [];
        $postBooster = [];
        $upperCount = 0;

        foreach ($words as $i => $word) {
            $lowered = $lower[$i];
            $upper = $ascii ? $lowered !== $word && strtoupper($word) === $word : self::isUpper($word);

            $isUpper[$i] = $upper;
            $lexicon[$i] = $rules->lexicon[$lowered] ?? ($hasFallback ? $this->fallbackValence($lowered) : null);
            $booster[$i] = $rules->boosters[$lowered] ?? null;
            $postBooster[$i] = $rules->postBoosters[$lowered] ?? null;

            if ($upper) {
                $upperCount++;
            }
        }

        if ($rules->dualRole !== []) {
            $this->resolveDualRoles($lower, $lexicon, $booster, $postBooster);
        }

        return new Sentence(
            lower: $lower,
            isUpper: $isUpper,
            lexicon: $lexicon,
            booster: $booster,
            postBooster: $postBooster,
            isCapsDifferential: $upperCount > 0 && $upperCount < count($words),
        );
    }

    private static function isUpper(string $word): bool
    {
        return preg_match('/\p{Lu}/u', $word) === 1 && preg_match('/[\p{Ll}\p{Lt}]/u', $word) === 0;
    }

    /**
     * Words that are not in the lexicon may still be a known word plus a clitic suffix
     * ("bagusnya") or a reduplication ("bagus-bagus", "bagus2"). Modifiers never fall back:
     * "sayangnya" ("unfortunately") is not "sayang" ("dear").
     */
    private function fallbackValence(string $word): ?float
    {
        $rules = $this->rules;

        if (isset($rules->negations[$word]) || isset($rules->boosters[$word]) || isset($rules->postBoosters[$word]) || isset($rules->contrasts[$word])) {
            return null;
        }

        foreach ($rules->clitics as $clitic) {
            if (str_ends_with($word, $clitic)) {
                $stem = rtrim(substr($word, 0, -strlen($clitic)), '-');

                if (isset($rules->lexicon[$stem])) {
                    return $rules->lexicon[$stem];
                }
            }
        }

        if (!$rules->reduplication) {
            return null;
        }

        if (str_ends_with($word, '2')) {
            return $rules->lexicon[substr($word, 0, -1)] ?? null;
        }

        $parts = explode('-', $word);

        return count($parts) === 2 && $parts[0] === $parts[1] ? $rules->lexicon[$parts[0]] ?? null : null;
    }

    /**
     * Decides, once per text, which role each dual-role word plays, so that a modifier
     * always targets a token that keeps its valence and pre-modifiers win over post-modifiers:
     *  1. right to left, a pre-modifier is a modifier if the next token is a sentiment word that
     *     is not itself a modifier (post-modifiers still count as sentiment words here);
     *  2. left to right, a post-modifier is a modifier if the previous token is a sentiment word
     *     that is not itself a modifier.
     * Any other dual-role word is a sentiment word.
     *
     * @param array<int, string> $lower
     * @param array<int, float|null> $lexicon
     * @param array<int, float|null> $booster
     * @param array<int, float|null> $postBooster
     */
    private function resolveDualRoles(array $lower, array &$lexicon, array &$booster, array &$postBooster): void
    {
        $dualRole = $this->rules->dualRole;
        $count = count($lower);
        $isModifier = array_fill(0, $count, false);

        for ($i = $count - 2; $i >= 0; $i--) {
            if (isset($dualRole[$lower[$i]]) && $booster[$i] !== null) {
                $isModifier[$i] = $lexicon[$i + 1] !== null && !$isModifier[$i + 1];
            }
        }

        for ($i = 1; $i < $count; $i++) {
            if (isset($dualRole[$lower[$i]]) && $postBooster[$i] !== null) {
                $isModifier[$i] = $lexicon[$i - 1] !== null && !$isModifier[$i - 1];
            }
        }

        foreach ($lower as $i => $word) {
            if (!isset($dualRole[$word])) {
                continue;
            }

            if ($isModifier[$i]) {
                $lexicon[$i] = null;
            } else {
                $booster[$i] = null;
                $postBooster[$i] = null;
            }
        }
    }

    /**
     * Modifiers and "kind of" are not sentiment words.
     */
    private function isModifier(Sentence $sentence, int $i): bool
    {
        return $sentence->booster[$i] !== null
            || $sentence->postBooster[$i] !== null
            || ($this->rules->englishQuirks && $sentence->lower[$i] === 'kind' && ($sentence->lower[$i + 1] ?? null) === 'of');
    }

    private function wordValence(Sentence $sentence, int $i): float
    {
        $lower = $sentence->lower;
        $quirks = $this->rules->englishQuirks;
        $base = (float) $sentence->lexicon[$i];
        $valence = $base;

        if ($quirks) {
            if ($lower[$i] === 'no' && ($sentence->lexicon[$i + 1] ?? null) !== null) {
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

        if ($sentence->isUpper[$i] && $sentence->isCapsDifferential) {
            $valence += $valence > 0 ? self::CAPS_INCREMENT : -self::CAPS_INCREMENT;
        }

        if ($this->rules->postBoosters !== []) {
            $valence = $this->applyPostBoosters($sentence, $i, $valence);
        }

        for ($start = 0; $start < 3; $start++) {
            $neighbour = $i - $start - 1;

            if ($i <= $start || $sentence->lexicon[$neighbour] !== null) {
                continue;
            }

            $scalar = $this->boosterScalar($sentence, $sentence->booster[$neighbour], $neighbour, $valence);

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

        return $quirks ? $this->leastCheck($valence, $sentence, $i) : $valence;
    }

    /**
     * A booster one or two positions after the word scales it like one in front of it.
     */
    private function applyPostBoosters(Sentence $sentence, int $i, float $valence): float
    {
        for ($distance = 1; $distance <= 2; $distance++) {
            $following = $i + $distance;

            if (!isset($sentence->postBooster[$following]) || $sentence->lexicon[$following] !== null) {
                continue;
            }

            $scalar = $this->boosterScalar($sentence, $sentence->postBooster[$following], $following, $valence);
            $valence += $distance === 2 ? $scalar * 0.95 : $scalar;
        }

        return $valence;
    }

    private function boosterScalar(Sentence $sentence, ?float $boost, int $position, float $valence): float
    {
        if ($boost === null) {
            return 0.0;
        }

        $scalar = $valence < 0 ? -$boost : $boost;

        if ($sentence->isUpper[$position] && $sentence->isCapsDifferential) {
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
     */
    private function leastCheck(float $valence, Sentence $sentence, int $i): float
    {
        $lower = $sentence->lower;

        if ($i > 1 && $sentence->lexicon[$i - 1] === null && $lower[$i - 1] === 'least') {
            return $lower[$i - 2] !== 'at' && $lower[$i - 2] !== 'very' ? $valence * self::NEGATION_SCALAR : $valence;
        }

        if ($i > 0 && $sentence->lexicon[$i - 1] === null && $lower[$i - 1] === 'least') {
            return $valence * self::NEGATION_SCALAR;
        }

        return $valence;
    }

    /**
     * Weighs the sentiments before the first contrast word half and the ones after it one and
     * a half, scaling by position (the reference scales by value and mis-scales duplicates).
     *
     * @param list<float> $sentiments
     * @param array<int, string> $lower
     *
     * @return list<float>
     */
    private function weighContrast(array $sentiments, array $lower): array
    {
        foreach ($lower as $contrastIndex => $word) {
            if (!isset($this->rules->contrasts[$word])) {
                continue;
            }

            foreach ($sentiments as $position => $sentiment) {
                if ($position < $contrastIndex) {
                    $sentiments[$position] = $sentiment * 0.5;
                } elseif ($position > $contrastIndex) {
                    $sentiments[$position] = $sentiment * 1.5;
                }
            }

            break;
        }

        return $sentiments;
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
