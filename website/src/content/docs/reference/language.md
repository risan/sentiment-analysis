---
title: Language
description: API reference for the Risan\Sentiment\Language enum with English and Indonesian cases, string codes, and ValueError on unknown codes.
---

```php
use Risan\Sentiment\Language;
```

`enum Language: string` selects the lexicon and rules used for an analysis.

## Cases

| Case | Value |
|---|---|
| `Language::English` | `'en'` |
| `Language::Indonesian` | `'id'` |

## Where it is accepted

[`Sentiment::analyze()`](/reference/sentiment/) and the [`Analyzer`](/reference/analyzer/) constructor take `Language|string`, so you can pass either the enum case or its code. English is the default.

```php
use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Language;
use Risan\Sentiment\Sentiment;

Sentiment::analyze('Filmnya bagus banget!', Language::Indonesian);
Sentiment::analyze('Filmnya bagus banget!', 'id');

(new Analyzer('id'))->language(); // Language::Indonesian
```

## Usage

Being a backed enum, `Language` has the native methods:

```php
Language::from('id');       // Language::Indonesian
Language::tryFrom('fr');    // null
Language::English->value;   // 'en'
Language::cases();          // [English, Indonesian]
```

An unknown code throws PHP's native `ValueError`, from `Language::from()` as well as from `Sentiment::analyze()` and `new Analyzer()`:

```php
Language::from('fr');                 // throws ValueError
Sentiment::analyze('Bonjour', 'fr');  // throws ValueError
```

See [Languages](/guides/languages/) for how each language is scored.
