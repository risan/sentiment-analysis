<?php

declare(strict_types=1);

namespace Risan\Sentiment\Internal;

use Risan\Sentiment\Language;

/**
 * Loads the compiled resources once per process and per language.
 *
 * @internal
 */
final class RuleBook
{
    /** @var array<string, Rules> */
    private static array $rules = [];

    public static function for(Language $language): Rules
    {
        return self::$rules[$language->value] ??= self::load($language);
    }

    private static function load(Language $language): Rules
    {
        $resources = dirname(__DIR__, 2) . '/resources';

        /** @var array<array-key, float> $lexicon */
        $lexicon = require "{$resources}/{$language->value}/lexicon.php";

        /** @var array<string, string> $emoji */
        $emoji = require "{$resources}/en/emoji.php";

        /** @var array{negations: array<string, true>, boosters: array<string, float>, postBoosters: array<string, float>, contrasts: array<string, true>, phrases: array<string, true>, dualRole: array<string, true>, specialCases: array<string, float>, clitics: list<string>, reduplication: bool, englishQuirks: bool} $rules */
        $rules = require "{$resources}/{$language->value}/rules.php";

        return new Rules(
            lexicon: $lexicon,
            emoji: $emoji,
            emojiPattern: self::emojiPattern($emoji),
            negations: $rules['negations'],
            boosters: $rules['boosters'],
            postBoosters: $rules['postBoosters'],
            contrasts: $rules['contrasts'],
            phrases: $rules['phrases'],
            dualRole: $rules['dualRole'],
            specialCases: $rules['specialCases'],
            clitics: $rules['clitics'],
            reduplication: $rules['reduplication'],
            englishQuirks: $rules['englishQuirks'],
        );
    }

    /**
     * @param array<string, string> $emoji
     */
    private static function emojiPattern(array $emoji): string
    {
        $characters = array_map(
            static fn(string $character): string => preg_quote($character, '/'),
            array_map(strval(...), array_keys($emoji)),
        );

        return '/[' . implode('', $characters) . ']/u';
    }
}
