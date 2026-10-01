---
title: Language
description: API reference for the Risan\Sentiment\Language enum with English and Indonesian cases, string codes, and ValueError on unknown codes.
---

`enum Language: string` selects the lexicon and the rules for an analysis.

```php
use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Language;
use Risan\Sentiment\Sentiment;

Sentiment::analyze('Filmnya bagus banget!', Language::Indonesian);
Sentiment::analyze('Filmnya bagus banget!', 'id');

(new Analyzer('id'))->language(); // Language::Indonesian
```

## Cases

| Case | Value |
|---|---|
| `Language::English` | `'en'` |
| `Language::Indonesian` | `'id'` |

## Where you can use it

[`Sentiment::analyze()`](/reference/sentiment/) and the [`Analyzer`](/reference/analyzer/) constructor take `Language|string`. Pass the enum case or its code. English is the default.

## Native enum methods

`Language` is a backed enum, so it has the native methods:

```php
Language::from('id');     // Language::Indonesian
Language::tryFrom('fr');  // null
Language::English->value; // 'en'
Language::cases();        // [English, Indonesian]
```

An unknown code throws PHP's native `ValueError`. This is true for `Language::from()`, `Sentiment::analyze()` and `new Analyzer()`:

```php
Language::from('fr');                // throws ValueError
Sentiment::analyze('Bonjour', 'fr'); // throws ValueError
```

See [Languages](/guides/languages/) for how the package scores each language.
