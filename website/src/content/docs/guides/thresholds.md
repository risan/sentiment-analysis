---
title: Thresholds
description: Control how strict the positive and negative labels are with Analyzer::withThreshold(). Default is 0.05; valid values are 0 up to, but not including, 1.
---

The label is decided by comparing the [compound score](/guides/understanding-scores/) against a **threshold**. The scores themselves never change; only the label does.

| Condition | Label |
|---|---|
| `compound > 0` and `compound >= threshold` | `Label::Positive` |
| `compound < 0` and `compound <= -threshold` | `Label::Negative` |
| anything else | `Label::Neutral` |

So with the default threshold of `0.05`, the **neutral band** is the open interval between -0.05 and +0.05: anything from `-0.0499` to `0.0499` is neutral. A compound of exactly `0.05` is positive and `-0.05` is negative.

## Change the threshold

Pass it to the constructor or use `withThreshold()`:

```php
use Risan\Sentiment\Analyzer;

$analyzer = new Analyzer(threshold: 0.1);

$strict = $analyzer->withThreshold(0.4);
```

A wider neutral band means fewer texts get a positive or negative label. It suits cases where a wrong confident label is costly, such as auto-replying to unhappy customers. A narrower band catches weaker signals, at the price of more false alarms.

| Threshold | Neutral band | Use when |
|---|---|---|
| `0` | only `compound = 0` | you want a label for every text with any signal |
| `0.05` (default) | -0.05 to 0.05 | general use, the VADER recommendation |
| `0.2` to `0.4` | wider | you only want clearly polarized texts |

## Limits

The threshold must be at least `0` and lower than `1`. Anything else throws an `InvalidArgumentException`:

```php
(new Analyzer())->withThreshold(1.0);  // InvalidArgumentException
(new Analyzer())->withThreshold(-0.1); // InvalidArgumentException
```

A text whose compound is exactly `0` is always neutral, even at threshold `0`.

## Skip the label

If you already have your own cutoffs, ignore the label and use the numbers:

```php
$result = $analyzer->analyze($text);

if ($result->compound >= 0.6) {
    // very happy
}
```
