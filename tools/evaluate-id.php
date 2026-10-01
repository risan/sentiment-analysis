<?php

declare(strict_types=1);

/*
 * Measures the Indonesian analyzer on IndoNLU SmSA, a labeled corpus of Indonesian reviews
 * and comments (https://github.com/IndoNLP/indonlu, Apache-2.0). The splits are downloaded
 * once into the system temp directory and are never committed.
 *
 * Usage: php tools/evaluate-id.php [--split=train|valid|test] [--errors=N] [--missing=N] [--english-fallback]
 *
 *   --split=NAME         which split to score (default valid). Keep "test" for the final number;
 *                        tune on "train" and "valid" only.
 *   --errors=N           also print the N most confident wrong predictions, to spot words to fix.
 *   --missing=N          instead of scoring, list the N most frequent tokens of train+valid that the
 *                        lexicon does not know and that are not rule words, with their label counts.
 *                        This is how words to add to tools/data/id/lexicon.tsv are found.
 *   --english-fallback   experiment: also score words missing from the Indonesian lexicon with the
 *                        English lexicon (minus tools/data/id/english-denylist.txt, if it exists).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Label;
use Risan\Sentiment\Language;

const DATASET_URL = 'https://raw.githubusercontent.com/IndoNLP/indonlu/master/dataset/smsa_doc-sentiment-prosa/';

const LABELS = ['positive', 'neutral', 'negative'];

/**
 * @return list<array{text: string, label: string}>
 */
function loadSplit(string $split): array
{
    $cacheDirectory = sys_get_temp_dir() . '/risan-sentiment-smsa';
    $path = "{$cacheDirectory}/{$split}.tsv";

    if (!is_file($path)) {
        if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0o775, true)) {
            throw new RuntimeException("Cannot create {$cacheDirectory}");
        }

        $contents = file_get_contents(DATASET_URL . "{$split}_preprocess.tsv");

        if ($contents === false || file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Cannot download the {$split} split");
        }
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    $examples = [];

    foreach ($lines as $line) {
        $separator = strrpos($line, "\t");

        if ($separator === false) {
            throw new RuntimeException("Malformed line in {$path}: {$line}");
        }

        $label = trim(substr($line, $separator + 1));

        if (!in_array($label, LABELS, true)) {
            throw new RuntimeException("Unknown label '{$label}' in {$path}");
        }

        $examples[] = ['text' => substr($line, 0, $separator), 'label' => $label];
    }

    return $examples;
}

/**
 * English words that must not score Indonesian text, because they are common Indonesian words.
 *
 * @return array<string, true>
 */
function englishDenylist(string $root): array
{
    $path = $root . '/tools/data/id/english-denylist.txt';

    if (!is_file($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    return array_fill_keys(array_filter($lines, static fn(string $line): bool => $line[0] !== '#'), true);
}

function analyzerFor(bool $englishFallback, string $root): Analyzer
{
    $analyzer = new Analyzer(Language::Indonesian);

    if (!$englishFallback) {
        return $analyzer;
    }

    /** @var array<array-key, float> $english */
    $english = require $root . '/resources/en/lexicon.php';
    /** @var array<array-key, float> $indonesian */
    $indonesian = require $root . '/resources/id/lexicon.php';
    /** @var array{negations: array<string, true>, boosters: array<string, float>, postBoosters: array<string, float>, contrasts: array<string, true>} $rules */
    $rules = require $root . '/resources/id/rules.php';
    $denied = englishDenylist($root) + $rules['negations'] + $rules['boosters'] + $rules['postBoosters'] + $rules['contrasts'];
    $borrowed = [];

    foreach ($english as $word => $valence) {
        $word = (string) $word;

        if (!isset($indonesian[$word]) && !isset($denied[$word]) && preg_match('/\s/u', $word) !== 1) {
            $borrowed[$word] = $valence;
        }
    }

    return $analyzer->withWords($borrowed);
}

/**
 * @param list<array{text: string, label: string}> $examples
 */
function evaluate(Analyzer $analyzer, array $examples, int $errorLimit): void
{
    /** @var array<string, array<string, int>> $confusion rows are the actual label, columns the predicted one */
    $confusion = array_fill_keys(LABELS, array_fill_keys(LABELS, 0));
    /** @var array<string, int> $actualCounts */
    $actualCounts = array_fill_keys(LABELS, 0);
    $errors = [];

    foreach ($examples as $example) {
        $result = $analyzer->analyze($example['text']);
        $predicted = $result->label->value;
        $confusion[$example['label']][$predicted]++;
        $actualCounts[$example['label']]++;

        if ($predicted !== $example['label']) {
            $errors[] = ['text' => $example['text'], 'expected' => $example['label'], 'predicted' => $predicted, 'compound' => $result->compound];
        }
    }

    $total = count($examples);
    $correct = array_sum(array_map(static fn(string $label): int => $confusion[$label][$label], LABELS));
    $f1 = [];

    foreach (LABELS as $label) {
        $truePositives = $confusion[$label][$label];
        $predictedCount = array_sum(array_column($confusion, $label));
        $precision = $predictedCount > 0 ? $truePositives / $predictedCount : 0.0;
        $recall = $actualCounts[$label] > 0 ? $truePositives / $actualCounts[$label] : 0.0;
        $f1[$label] = $precision + $recall > 0 ? 2 * $precision * $recall / ($precision + $recall) : 0.0;
    }

    $majority = array_search(max($actualCounts), $actualCounts, true);
    $baselineAccuracy = max($actualCounts) / $total;
    $baselineF1 = [];

    foreach (LABELS as $label) {
        $baselineF1[$label] = $label === $majority ? 2 * $baselineAccuracy / (1 + $baselineAccuracy) : 0.0;
    }

    printf("examples          %d\n", $total);
    printf("accuracy          %.4f\n", $correct / $total);
    printf("macro-F1          %.4f\n", array_sum($f1) / count($f1));

    foreach (LABELS as $label) {
        printf("F1 %-14s %.4f   (%d examples)\n", $label, $f1[$label], $actualCounts[$label]);
    }

    printf("majority baseline accuracy %.4f, macro-F1 %.4f (always \"%s\")\n", $baselineAccuracy, array_sum($baselineF1) / count($baselineF1), $majority);
    echo "confusion (rows actual, columns predicted: positive neutral negative)\n";

    foreach (LABELS as $label) {
        printf("%-9s %s\n", $label, implode(' ', array_map(static fn(int $count): string => sprintf('%5d', $count), $confusion[$label])));
    }

    usort($errors, static fn(array $left, array $right): int => abs($right['compound']) <=> abs($left['compound']));

    foreach (array_slice($errors, 0, $errorLimit) as $error) {
        printf("\n[%s, predicted %s, compound %.4f]\n%s\n", $error['expected'], $error['predicted'], $error['compound'], mb_substr($error['text'], 0, 400, 'UTF-8'));
    }
}

/**
 * @param list<array{text: string, label: string}> $examples
 */
function listMissingTokens(Analyzer $analyzer, array $examples, int $limit, string $root): void
{
    /** @var array{negations: array<string, true>, boosters: array<string, float>, postBoosters: array<string, float>, contrasts: array<string, true>} $rules */
    $rules = require $root . '/resources/id/rules.php';
    $ruleWords = $rules['negations'] + $rules['boosters'] + $rules['postBoosters'] + $rules['contrasts'];
    $counts = [];

    foreach ($examples as $example) {
        preg_match_all('/\p{L}[\p{L}\p{N}]*(?:-\p{L}[\p{L}\p{N}]*)*/u', mb_strtolower($example['text'], 'UTF-8'), $matches);

        foreach ($matches[0] as $token) {
            $counts[$token] ??= ['total' => 0, 'positive' => 0, 'neutral' => 0, 'negative' => 0];
            $counts[$token]['total']++;
            $counts[$token][$example['label']]++;
        }
    }

    uasort($counts, static fn(array $left, array $right): int => $right['total'] <=> $left['total']);
    $listed = 0;

    foreach ($counts as $token => $count) {
        $token = (string) $token;

        if (isset($ruleWords[$token]) || $analyzer->analyze($token)->compound !== 0.0) {
            continue;
        }

        printf("%-24s %5d  pos %4d  neu %4d  neg %4d\n", $token, $count['total'], $count['positive'], $count['neutral'], $count['negative']);

        if (++$listed >= $limit) {
            break;
        }
    }
}

$options = getopt('', ['split::', 'errors::', 'missing::', 'english-fallback']);
$root = dirname(__DIR__);
$analyzer = analyzerFor(isset($options['english-fallback']), $root);

if (isset($options['missing'])) {
    listMissingTokens($analyzer, [...loadSplit('train'), ...loadSplit('valid')], (int) $options['missing'], $root);

    exit(0);
}

$split = $options['split'] ?? 'valid';

if (!in_array($split, ['train', 'valid', 'test'], true)) {
    fwrite(STDERR, "--split must be train, valid or test\n");

    exit(1);
}

echo "SmSA {$split} split\n";
evaluate($analyzer, loadSplit($split), (int) ($options['errors'] ?? 0));
