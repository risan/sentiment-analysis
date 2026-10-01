---
title: Long text
description: How to score long reviews and articles by splitting them into sentences, analyzing each one, and averaging the compound scores.
---

The package works best on short text: one sentence or a few. For a long text, score each sentence and average the results.

```php
use Risan\Sentiment\Analyzer;

function averageCompound(Analyzer $analyzer, string $text): float
{
    $sentences = preg_split(
        '/(?<=[.!?])\s+/u',
        trim($text),
        -1,
        PREG_SPLIT_NO_EMPTY,
    );

    if ($sentences === false || $sentences === []) {
        return 0.0;
    }

    $total = 0.0;

    foreach ($sentences as $sentence) {
        $total += $analyzer->analyze($sentence)->compound;
    }

    return $total / count($sentences);
}

$review = 'The room was clean and the staff were lovely! '
    . 'The bed was comfortable. Breakfast was terrible.';

round(averageCompound(new Analyzer(), $review), 4); // 0.2705
```

The average is a float from -1 to 1. You can compare it with the same [thresholds](/guides/thresholds/) as a normal compound score.

## Why not score the whole text?

On a long text, the compound score drifts toward -1 or +1 as more words pile up. A few strong words can make a long review look more extreme than it is.

```php
Sentiment::analyze($review)->compound; // 0.7901
```

The whole review scores `0.7901`, even though one of its three sentences is negative. The average, `0.2705`, shows the mix better.

Per-sentence scoring also helps in these ways:

- Each sentence gets its own negation, contrast and emphasis handling. A `not` in one sentence cannot reach into the next.
- Every sentence has the same weight. One long angry paragraph does not drown out five calm ones.
- You get each sentence score for free. Keep them to show which sentences drive the result.

## Weighting and ranking

A plain average treats `Thanks.` and a detailed complaint the same. If that matters, weight by length, drop very short sentences, or pick the most extreme sentences:

```php
$scores = array_map(
    fn (string $s): float => $analyzer->analyze($s)->compound,
    $sentences,
);

$worst = min($scores);
$best = max($scores);
```

## Splitting sentences

Splitting text into sentences is a hard problem on its own. The regular expression above works for normal prose. It also splits after abbreviations such as `Dr.` and `e.g.`.

If you need better splitting, use `IntlBreakIterator` from the `intl` extension, or a splitter made for your language. Then pass each sentence to the analyzer.
