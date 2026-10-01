<?php

declare(strict_types=1);

namespace Risan\Sentiment;

final class Sentiment
{
    /** @var array<string, Analyzer> */
    private static array $analyzers = [];

    private function __construct() {}

    public static function analyze(string $text, Language|string $language = Language::English): Result
    {
        $language = $language instanceof Language ? $language : Language::from($language);

        return (self::$analyzers[$language->value] ??= new Analyzer($language))->analyze($text);
    }
}
