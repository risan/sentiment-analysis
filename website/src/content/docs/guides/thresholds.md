---
title: Thresholds
description: Control how strict the positive and negative labels are with Analyzer::withThreshold(). Default is 0.05; valid values are 0 up to, but not including, 1.
---

A **threshold** decides how strong a score must be to get a positive or negative label. The scores never change. Only the label does.

```php
use Risan\Sentiment\Analyzer;

$text = 'The movie was good.';

$default = new Analyzer();

$default->analyze($text)->compound; // 0.4404
$default->analyze($text)->label;    // Label::Positive

$strict = $default->withThreshold(0.5);

$strict->analyze($text)->compound; // 0.4404
$strict->analyze($text)->label;    // Label::Neutral
```

The compound score `0.4404` is below `0.5`, so the strict analyzer calls the text neutral.

## How the label is chosen

| Condition | Label |
|---|---|
| `compound > 0` and `compound >= threshold` | `Label::Positive` |
| `compound < 0` and `compound <= -threshold` | `Label::Negative` |
| anything else | `Label::Neutral` |

With the default threshold of `0.05`, the **neutral band** runs from -0.05 to +0.05. Any compound from `-0.0499` to `0.0499` is neutral. A compound of exactly `0.05` is positive. A compound of `-0.05` is negative.

## Change the threshold

Pass it to the constructor, or use `withThreshold()`:

```php
use Risan\Sentiment\Analyzer;

$analyzer = new Analyzer(threshold: 0.1);

$strict = $analyzer->withThreshold(0.4);
```

A wider neutral band means fewer positive and negative labels. Use it when a wrong, confident label costs a lot. An example is an automatic reply to unhappy customers. A narrower band catches weaker signals, but gives more false alarms.

| Threshold | Neutral band | Use when |
|---|---|---|
| `0` | only `compound = 0` | you want a label for every text with any signal |
| `0.05` (default) | -0.05 to 0.05 | general use, the VADER recommendation |
| `0.2` to `0.4` | wider | you only want clearly polarized texts |

## Limits

The threshold must be at least `0` and below `1`. Anything else throws an `InvalidArgumentException`:

```php
use Risan\Sentiment\Analyzer;

(new Analyzer())->withThreshold(1.0);  // InvalidArgumentException
(new Analyzer())->withThreshold(-0.1); // InvalidArgumentException
```

A text with a compound of exactly `0` is always neutral, even at threshold `0`.

## Skip the label

If you have your own cutoffs, ignore the label and use the numbers:

```php
use Risan\Sentiment\Analyzer;

$analyzer = new Analyzer();
$result = $analyzer->analyze('The movie was good.');

if ($result->compound >= 0.6) {
    // very happy
}
```
