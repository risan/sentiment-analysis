<?php

declare(strict_types=1);

/*
 * Compiles the source data in tools/data into the plain PHP arrays that ship in resources/.
 *
 * Usage: php tools/build-lexicons.php [output-directory]
 *
 * The output is deterministic: running it twice gives identical bytes.
 */

ini_set('serialize_precision', '-1');

const PYTHON_WHITESPACE = " \t\n\r\x0B\f";

/** Python's string.punctuation, which the engine strips from both ends of a token. */
const PUNCTUATION = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';

const MAX_VALENCE = 4.0;

const RULE_DEFAULTS = [
    'negations' => [],
    'boosters' => [],
    'postBoosters' => [],
    'contrasts' => [],
    'phrases' => [],
    'dualRole' => [],
    'specialCases' => [],
    'clitics' => [],
    'reduplication' => false,
    'englishQuirks' => false,
];

/** Lists in the source that the engine looks up as sets. */
const RULE_SETS = ['negations', 'contrasts', 'phrases', 'dualRole'];

/** Word to number maps. */
const RULE_MAPS = ['boosters', 'postBoosters', 'specialCases'];

const RULE_FLAGS = ['englishQuirks', 'reduplication'];

/**
 * Reads vader_lexicon.txt exactly like VADER's make_lex_dict: keys are verbatim
 * (never lower-cased) and the last line wins for a duplicate key.
 *
 * @return array<array-key, float>
 */
function readVaderLexicon(string $path): array
{
    $lexicon = [];

    foreach (readLines($path) as $line) {
        if ($line === '') {
            continue;
        }

        $fields = explode("\t", trim($line, PYTHON_WHITESPACE));

        if (count($fields) < 2) {
            throw new RuntimeException("Malformed lexicon line in {$path}: {$line}");
        }

        $lexicon[$fields[0]] = (float) $fields[1];
    }

    ksort($lexicon, SORT_STRING);

    return $lexicon;
}

/**
 * Keeps single code point emoji only: the reference walks the text code point
 * by code point, so a multi code point key can never match.
 *
 * @return array<string, string>
 */
function readEmojiLexicon(string $path): array
{
    $emoji = [];

    foreach (readLines($path) as $line) {
        $fields = explode("\t", trim($line, PYTHON_WHITESPACE));

        if (count($fields) < 2) {
            throw new RuntimeException("Malformed emoji line in {$path}: {$line}");
        }

        if (mb_strlen($fields[0], 'UTF-8') === 1) {
            $emoji[$fields[0]] = $fields[1];
        }
    }

    return $emoji;
}

/**
 * Reads word<TAB>valence lines; '#' lines and blank lines are comments.
 *
 * @return array<string, float>
 */
function readAuthoredLexicon(string $path): array
{
    $lexicon = [];

    foreach (readLines($path) as $number => $line) {
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $where = "{$path}:" . ($number + 1);
        $fields = explode("\t", $line);

        if (count($fields) !== 2 || !is_numeric($fields[1])) {
            throw new RuntimeException("{$where}: expected word<TAB>valence");
        }

        [$word, $valence] = [$fields[0], (float) $fields[1]];

        if ($word === '' || $word !== mb_strtolower($word, 'UTF-8') || preg_match('/\s/u', $word) !== 0) {
            throw new RuntimeException("{$where}: the word must be lower-case and free of whitespace");
        }

        if ($valence === 0.0 || abs($valence) > MAX_VALENCE) {
            throw new RuntimeException("{$where}: the valence of {$word} must be non-zero and within -4..4");
        }

        if (isset($lexicon[$word])) {
            throw new RuntimeException("{$where}: duplicate word {$word}");
        }

        $lexicon[$word] = $valence;
    }

    return $lexicon;
}

/**
 * @return list<string>
 */
function readWordList(string $path): array
{
    return array_values(array_filter(
        readLines($path),
        static fn(string $line): bool => $line !== '' && $line[0] !== '#',
    ));
}

/**
 * @return list<string>
 */
function readLines(string $path): array
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    return explode("\n", rtrim($contents, "\n"));
}

/**
 * Turns the readable rule source (lists of words) into the lookup maps the engine uses.
 * Every key is always present in the output, so a resource file never needs defaults.
 *
 * @param array<string, mixed> $source
 *
 * @return array<string, mixed>
 */
function compileRules(array $source, string $path): array
{
    $unknown = array_diff(array_keys($source), array_keys(RULE_DEFAULTS));

    if ($unknown !== []) {
        throw new RuntimeException("Unknown rule keys in {$path}: " . implode(', ', $unknown));
    }

    $compiled = [];

    foreach (RULE_DEFAULTS as $key => $default) {
        $value = $source[$key] ?? $default;

        if (in_array($key, RULE_FLAGS, true)) {
            if (!is_bool($value)) {
                throw new RuntimeException("{$key} in {$path} must be a bool");
            }

            $compiled[$key] = $value;

            continue;
        }

        if (!is_array($value)) {
            throw new RuntimeException("{$key} in {$path} must be an array");
        }

        $compiled[$key] = in_array($key, RULE_MAPS, true)
            ? compileNumberMap($value, "{$key} in {$path}")
            : compileWordList($value, "{$key} in {$path}", in_array($key, RULE_SETS, true));
    }

    $phrases = $compiled['phrases'];
    $boosters = $compiled['boosters'];
    assert(is_array($phrases) && is_array($boosters));

    foreach (array_keys($phrases) as $phrase) {
        $phrase = (string) $phrase;

        if (substr_count($phrase, ' ') !== 1 || !isset($boosters[$phrase])) {
            throw new RuntimeException("The phrase '{$phrase}' in {$path} must have two words and be listed under boosters");
        }
    }

    return $compiled;
}

/**
 * @param array<array-key, mixed> $map
 *
 * @return array<string, float>
 */
function compileNumberMap(array $map, string $where): array
{
    $numbers = [];

    foreach ($map as $word => $number) {
        if (!is_float($number) && !is_int($number)) {
            throw new RuntimeException("{$where}['{$word}'] must be a number");
        }

        $numbers[(string) $word] = (float) $number;
    }

    return $numbers;
}

/**
 * @param array<array-key, mixed> $words
 *
 * @return array<array-key, mixed>
 */
function compileWordList(array $words, string $where, bool $asSet): array
{
    foreach ($words as $word) {
        if (!is_string($word) || $word === '') {
            throw new RuntimeException("{$where} must hold non-empty strings");
        }
    }

    return $asSet ? array_fill_keys($words, true) : array_values($words);
}

/**
 * The Indonesian lexicon is the authored word list, plus two things borrowed from the MIT
 * VADER lexicon because they are language-neutral:
 * - emoticons and symbols (keys without a run of two letters, with a non-alphanumeric character);
 * - the words that occur in single code point emoji descriptions, since the engine scores an
 *   emoji through its English description. A reviewed denylist drops Indonesian collisions.
 * An authored word always wins over a borrowed one.
 *
 * @param array<array-key, float> $englishLexicon
 * @param array<string, string> $emoji
 * @param array<string, mixed> $rules compiled Indonesian rules
 *
 * @return array<array-key, float>
 */
function buildIndonesianLexicon(string $root, array $englishLexicon, array $emoji, array $rules): array
{
    $authored = readAuthoredLexicon($root . '/tools/data/id/lexicon.tsv');
    $denylist = array_flip(readWordList($root . '/tools/data/id/emoji-denylist.txt'));
    $ruleWords = ruleWords($rules);

    assertRuleWordsStayOutOfLexicon($authored, $ruleWords, $rules);

    $emoticons = [];

    foreach ($englishLexicon as $key => $valence) {
        if (isEmoticon((string) $key)) {
            $emoticons[$key] = $valence;
        }
    }

    $emojiWords = [];

    foreach (emojiDescriptionWords($emoji) as $word) {
        if (isset($englishLexicon[$word]) && !isset($denylist[$word]) && !isset($ruleWords[$word])) {
            $emojiWords[$word] = $englishLexicon[$word];
        }
    }

    $lexicon = array_replace($emoticons, $emojiWords, $authored);
    ksort($lexicon, SORT_STRING);

    return $lexicon;
}

function isEmoticon(string $key): bool
{
    return preg_match('/\s/u', $key) !== 1
        && preg_match('/^\d+$/', $key) !== 1
        && preg_match('/\p{L}{2}/u', $key) !== 1
        && preg_match('/[^\p{L}\p{N}]/u', $key) === 1;
}

/**
 * The words the engine would look up for each emoji description, tokenized like the engine does.
 *
 * @param array<string, string> $emoji
 *
 * @return list<string>
 */
function emojiDescriptionWords(array $emoji): array
{
    $words = [];

    foreach ($emoji as $description) {
        foreach (explode(' ', $description) as $token) {
            $stripped = trim($token, PUNCTUATION);
            $words[mb_strtolower(mb_strlen($stripped, 'UTF-8') <= 2 ? $token : $stripped, 'UTF-8')] = true;
        }
    }

    return array_map(strval(...), array_keys($words));
}

/**
 * @param array<string, mixed> $rules
 *
 * @return array<string, true>
 */
function ruleWords(array $rules): array
{
    $words = [];

    foreach (['negations', 'boosters', 'postBoosters', 'contrasts'] as $key) {
        $group = $rules[$key];
        assert(is_array($group));

        foreach (array_keys($group) as $word) {
            $words[(string) $word] = true;
        }
    }

    return $words;
}

/**
 * @param array<string, float> $authored
 * @param array<string, true> $ruleWords
 * @param array<string, mixed> $rules
 */
function assertRuleWordsStayOutOfLexicon(array $authored, array $ruleWords, array $rules): void
{
    $dualRole = $rules['dualRole'];
    assert(is_array($dualRole));

    foreach (array_keys($authored) as $word) {
        if (isset($ruleWords[$word]) && !isset($dualRole[$word])) {
            throw new RuntimeException("{$word} is a rule word, so it must not be in the lexicon unless it is listed under dualRole");
        }
    }

    foreach (array_keys($dualRole) as $word) {
        if (!isset($authored[$word]) || !isset($ruleWords[$word])) {
            throw new RuntimeException("The dual-role word {$word} must be both in the lexicon and a rule word");
        }
    }
}

function exportValue(mixed $value, int $depth = 0): string
{
    if (!is_array($value)) {
        return var_export($value, true);
    }

    if ($value === []) {
        return '[]';
    }

    $indent = str_repeat('    ', $depth + 1);
    $isList = array_is_list($value);
    $lines = [];

    foreach ($value as $key => $item) {
        $prefix = $isList ? '' : (is_int($key) ? $key : var_export($key, true)) . ' => ';
        $lines[] = $indent . $prefix . exportValue($item, $depth + 1) . ',';
    }

    return "[\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $depth) . ']';
}

function writeResource(string $path, mixed $data, string $source): void
{
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0o775, true)) {
        throw new RuntimeException("Cannot create {$directory}");
    }

    $contents = "<?php\n\ndeclare(strict_types=1);\n\n// Generated by tools/build-lexicons.php from {$source}. Do not edit.\n\nreturn "
        . exportValue($data)
        . ";\n";

    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Cannot write {$path}");
    }
}

/**
 * @return array<string, mixed>
 */
function loadRuleSource(string $path): array
{
    $source = require $path;

    if (!is_array($source)) {
        throw new RuntimeException("{$path} must return an array");
    }

    return array_combine(array_map(strval(...), array_keys($source)), $source);
}

$root = dirname(__DIR__);
$output = $argv[1] ?? $root . '/resources';

$englishLexicon = readVaderLexicon($root . '/tools/data/en/vader_lexicon.txt');
$emoji = readEmojiLexicon($root . '/tools/data/en/emoji_utf8_lexicon.txt');
$englishRules = compileRules(loadRuleSource($root . '/tools/data/en/rules.php'), 'tools/data/en/rules.php');
$indonesianRules = compileRules(loadRuleSource($root . '/tools/data/id/rules.php'), 'tools/data/id/rules.php');

writeResource($output . '/en/lexicon.php', $englishLexicon, 'tools/data/en/vader_lexicon.txt');
writeResource($output . '/en/emoji.php', $emoji, 'tools/data/en/emoji_utf8_lexicon.txt');
writeResource($output . '/en/rules.php', $englishRules, 'tools/data/en/rules.php');
writeResource(
    $output . '/id/lexicon.php',
    buildIndonesianLexicon($root, $englishLexicon, $emoji, $indonesianRules),
    'tools/data/id/lexicon.tsv, tools/data/id/emoji-denylist.txt and the VADER emoticons and emoji words',
);
writeResource($output . '/id/rules.php', $indonesianRules, 'tools/data/id/rules.php');
