---
title: Long text
description: How to score long reviews and articles by splitting them into sentences, analyzing each one, and averaging the compound scores.
---

The model was designed for short, informal text: a sentence or a few. On long text the normalized compound score drifts toward ±1 as more words pile up, so a long review with a few strong words can look more extreme than it is, and a mixed article can hide its structure.

For anything longer than a short paragraph, analyze it **per sentence and average**.

## Per-sentence averaging

```php
use Risan\Sentiment\Analyzer;

function averageCompound(Analyzer $analyzer, string $text): float
{
    $sentences = preg_split('/(?<=[.!?])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

    if ($sentences === false || $sentences === []) {
        return 0.0;
    }

    $total = 0.0;

    foreach ($sentences as $sentence) {
        $total += $analyzer->analyze($sentence)->compound;
    }

    return $total / count($sentences);
}

$review = 'The room was spotless. The staff were lovely! Breakfast was cold, though.';

averageCompound(new Analyzer(), $review);
```

The average is a float from -1 to 1, so you can compare it with the same [thresholds](/guides/thresholds/) as a normal compound score.

## Why it helps

- Each sentence gets its own negation, contrast and emphasis handling, so a `not` in one sentence cannot reach into the next.
- Every sentence has equal weight, so one long angry paragraph does not drown out five calm ones.
- You get the individual scores for free. Keep them to show which sentences drive the result.

## Weighting and ranking

A plain average treats `Thanks.` and a detailed complaint the same. If that matters, weight by length, drop very short sentences, or pick the most extreme sentences:

```php
$scores = array_map(
    fn (string $sentence): float => $analyzer->analyze($sentence)->compound,
    $sentences,
);

$worst = min($scores);
$best = max($scores);
```

## A note on splitting

Sentence splitting is a text problem of its own. The regular expression above is fine for typical prose, but it will split after abbreviations such as `Dr.` and `e.g.`. If your text needs better splitting, use `IntlBreakIterator` from the `intl` extension or a splitter that suits your language, and pass the sentences to the analyzer.
