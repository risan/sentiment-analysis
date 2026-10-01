---
title: Analyzer
description: API reference for Risan\Sentiment\Analyzer, the configurable, immutable analyzer with withWords(), withoutWords() and withThreshold().
---

`final class Analyzer` scores text for one language. You can add words and change the threshold.

```php
use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Language;

$analyzer = (new Analyzer(Language::Indonesian))
    ->withWords(['cuan' => 2.5, 'bapuk' => -2.0])
    ->withoutWords(['kasar'])
    ->withThreshold(0.1);

$result = $analyzer->analyze('Investasinya cuan!');

$result->label;    // Label::Positive
$result->compound; // 0.5848
```

An analyzer is **immutable**. Every `with*()` method returns a new instance and leaves the original unchanged. It keeps no state between calls, so you can share one instance and reuse it.

## Constructor

Creates an analyzer for one language.

```php
$english = new Analyzer();
$indonesian = new Analyzer(Language::Indonesian);
$strict = new Analyzer('en', threshold: 0.3);
```

```php
public function __construct(
    Language|string $language = Language::English,
    float $threshold = 0.05,
)
```

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$language` | `Language\|string` | A [`Language`](/reference/language/) case or its code (`'en'`, `'id'`). Defaults to English. |
| `$threshold` | `float` | The label threshold, from `0` up to but not including `1`. Defaults to `0.05`. See [Thresholds](/guides/thresholds/). |

**Throws**

| Exception | When |
|---|---|
| `ValueError` | `$language` is a string that is not a known code. |
| `InvalidArgumentException` | `$threshold` is below `0`, or is `1` or higher. |

## Methods

### analyze()

Scores a text and returns a [`Result`](/reference/result/).

```php
$result = (new Analyzer())->analyze('The food was not good.');

$result->label;    // Label::Negative
$result->compound; // -0.3412
```

```php
public function analyze(string $text): Result
```

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$text` | `string` | The text to analyze. Any length, including empty. |

**Returns** a [`Result`](/reference/result/).

Empty or whitespace-only text returns all zeros and a neutral label. Invalid UTF-8 never throws. The package drops the invalid bytes and scores the rest.

### withWords()

Returns a new analyzer with words added to the lexicon, or with their scores replaced.

```php
$analyzer = (new Analyzer(Language::Indonesian))
    ->withWords(['cuan' => 2.5, 'bapuk' => -2.0]);

$analyzer->withWords(['great' => 9]); // throws InvalidArgumentException
```

```php
public function withWords(array $words): static
```

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$words` | `array<array-key, float\|int>` | Map of `word => valence`. A valence is a score from `-4` to `4`. |

The package lower-cases the keys. A numeric key such as `'1337'` works, even though PHP turns it into an int.

**Returns** a new `Analyzer`.

**Throws** `InvalidArgumentException` when:

- a valence is below `-4`, above `4` or not a number;
- a key is empty;
- a key contains whitespace. A word is a single token, so such a key could never match.

See [Customizing the lexicon](/guides/customizing-the-lexicon/).

### withoutWords()

Returns a new analyzer without the given words. They then count as neutral words.

```php
$analyzer = (new Analyzer())->withoutWords(['kill']);
```

```php
public function withoutWords(array $words): static
```

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$words` | `list<string>` | Words to remove. |

**Returns** a new `Analyzer`.

### withThreshold()

Returns a new analyzer with a different label threshold. The scores do not change. Only the label does.

```php
// The neutral band is now -0.1 to 0.1.
$analyzer = (new Analyzer())->withThreshold(0.1);
```

```php
public function withThreshold(float $threshold): static
```

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$threshold` | `float` | From `0` up to but not including `1`. |

**Returns** a new `Analyzer`.

**Throws** `InvalidArgumentException` when the threshold is outside that range.

### language()

Returns the [`Language`](/reference/language/) of this analyzer. It is always an enum case, even when you passed a string code.

```php
(new Analyzer('id'))->language(); // Language::Indonesian
```

```php
public function language(): Language
```

**Returns** a [`Language`](/reference/language/).

## Immutability

```php
$base = new Analyzer();
$tuned = $base->withThreshold(0.3);

$base === $tuned; // false: $base keeps its threshold of 0.05
```
