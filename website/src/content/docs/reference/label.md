---
title: Label
description: API reference for the Risan\Sentiment\Label enum with its Positive, Negative and Neutral cases, and how a label is chosen.
---

`enum Label: string` is the verdict of an analysis. You find it in [`Result::$label`](/reference/result/).

```php
use Risan\Sentiment\Label;
use Risan\Sentiment\Sentiment;

$result = Sentiment::analyze('This package is awesome!');

$result->label === Label::Positive; // true
$result->label->value;              // 'positive'
$result->label->name;               // 'Positive'

$message = match ($result->label) {
    Label::Positive => 'Glad you liked it!',
    Label::Negative => 'Sorry to hear that.',
    Label::Neutral => 'Thanks for the feedback.',
};
```

## Cases

| Case | Value |
|---|---|
| `Label::Positive` | `'positive'` |
| `Label::Negative` | `'negative'` |
| `Label::Neutral` | `'neutral'` |

## How the label is chosen

The label comes from the compound score and the [threshold](/guides/thresholds/) of the analyzer:

- `Label::Positive` when `compound > 0` and `compound >= threshold`.
- `Label::Negative` when `compound < 0` and `compound <= -threshold`.
- `Label::Neutral` otherwise.

With the default threshold of `0.05`, a compound of `0.05` is positive and `0.049` is neutral. A compound of exactly zero is always neutral.

## Native enum methods

`Label` is a backed enum, so it has the native methods:

```php
Label::from('negative'); // Label::Negative
Label::tryFrom('mixed'); // null
Label::cases();          // [Positive, Negative, Neutral]
```

`Label::from()` throws a `ValueError` for an unknown value. Use `tryFrom()` when the value may be invalid.
