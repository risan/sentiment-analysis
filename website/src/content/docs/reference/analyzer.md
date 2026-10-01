---
title: Analyzer
description: API reference for Risan\Sentiment\Analyzer, the configurable, immutable analyzer with withWords(), withoutWords() and withThreshold().
---

```php
use Risan\Sentiment\Analyzer;
```

`final class Analyzer` scores text for one language. It is **immutable**: every `with*()` method returns a new instance and leaves the original unchanged. An analyzer keeps no state between calls, so one instance can be shared and reused freely.

```php
$analyzer = new Analyzer();                      // English
$analyzer = new Analyzer(Language::Indonesian);  // or new Analyzer('id')

$analyzer = $analyzer
    ->withWords(['cuan' => 2.5, 'bapuk' => -2.0])
    ->withoutWords(['kill'])
    ->withThreshold(0.1);

$result = $analyzer->analyze('Investasinya cuan!');
```

## Constructor

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
| `InvalidArgumentException` | `$threshold` is below `0` or is `1` or higher. |

```php
$english = new Analyzer();
$indonesian = new Analyzer(Language::Indonesian);
$strict = new Analyzer('en', threshold: 0.3);
```

## Methods

### analyze()

```php
public function analyze(string $text): Result
```

Scores a text and returns a [`Result`](/reference/result/).

| Name | Type | Description |
|---|---|---|
| `$text` | `string` | The text to analyze. Any length, including empty. |

Empty or whitespace-only text returns all zeros and a neutral label. Invalid UTF-8 never throws: invalid bytes are dropped and the rest is scored.

```php
$result = (new Analyzer())->analyze('The food was not good.');

$result->label;    // Label::Negative
$result->compound; // a negative float
```

### withWords()

```php
public function withWords(array $words): static
```

Returns a new analyzer with words added to the lexicon or their valences overridden.

| Name | Type | Description |
|---|---|---|
| `$words` | `array<array-key, float\|int>` | Map of `word => valence`. Valences run from `-4` to `4`. |

Keys are lower-cased. A numeric key such as `'1337'` works, even though PHP turns it into an int.

**Throws** `InvalidArgumentException` when:

- a valence is below `-4`, above `4` or not a number;
- a key is empty;
- a key contains whitespace, because a word is a single token and such a key could never match.

```php
$analyzer = (new Analyzer(Language::Indonesian))
    ->withWords(['cuan' => 2.5, 'bapuk' => -2.0]);

$analyzer->withWords(['great' => 9]); // throws InvalidArgumentException
```

See [Customizing the lexicon](/guides/customizing-the-lexicon/).

### withoutWords()

```php
public function withoutWords(array $words): static
```

Returns a new analyzer with the given words removed from the lexicon. They then count as neutral words.

| Name | Type | Description |
|---|---|---|
| `$words` | `list<string>` | Words to remove. |

```php
$analyzer = (new Analyzer())->withoutWords(['kill']);
```

### withThreshold()

```php
public function withThreshold(float $threshold): static
```

Returns a new analyzer with a different label threshold. The scores do not change, only the label.

| Name | Type | Description |
|---|---|---|
| `$threshold` | `float` | From `0` up to but not including `1`. |

**Throws** `InvalidArgumentException` when the threshold is outside that range.

```php
$analyzer = (new Analyzer())->withThreshold(0.1); // neutral band is (-0.1, 0.1)
```

### language()

```php
public function language(): Language
```

Returns the [`Language`](/reference/language/) of this analyzer, always as an enum case, even when you passed a string code.

```php
(new Analyzer('id'))->language(); // Language::Indonesian
```

## Immutability

```php
$base = new Analyzer();
$tuned = $base->withThreshold(0.3);

$base === $tuned; // false: $base is untouched and keeps threshold 0.05
```
