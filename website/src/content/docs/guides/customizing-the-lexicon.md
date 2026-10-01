---
title: Customizing the lexicon
description: Add, override or remove words with Analyzer::withWords() and withoutWords() to adapt sentiment scores to your domain, slang or brand vocabulary.
---

The lexicon is the list of words and the sentiment valence each carries. The defaults are good for general text, but your domain may disagree. In a gaming forum `sick` is praise. In a medical text `positive` may be bad news. For that, use an [`Analyzer`](/reference/analyzer/).

## Valence scale

A valence is a number from **-4** (extremely negative) to **+4** (extremely positive). As a rough guide:

| Valence | Feels like |
|---|---|
| ±0.5 | faint |
| ±1.5 | mild |
| ±2.5 | clear |
| ±3.5 | extreme |

Pick values by comparing with words the lexicon already knows: if `good` is about 1.9 and `great` about 3.1, a word that means "better than good, not quite great" belongs near 2.5.

## Add or override words

`withWords()` takes an array of `word => valence`. New words are added, and existing words get the new value.

```php
use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Language;

$analyzer = (new Analyzer(Language::Indonesian))
    ->withWords([
        'cuan' => 2.5,
        'bapuk' => -2.0,
    ]);

$analyzer->analyze('Investasinya cuan!')->isPositive(); // true
```

Keys are lower-cased for you, and matching ignores the case of the text, so `cuan`, `Cuan` and `CUAN` all hit the same entry. Values can be ints or floats.

Override a word that does not fit your domain:

```php
$analyzer = (new Analyzer())->withWords(['sick' => 2.0]);

$analyzer->analyze('That trick was sick!')->isPositive(); // true
```

## Remove words

`withoutWords()` takes a list of words to drop from the lexicon. They then count as plain neutral words.

```php
$analyzer = (new Analyzer())->withoutWords(['kill']);

$analyzer->analyze('We will kill it at the launch.')->isNegative(); // false
```

## Rules for words

A key must be a single token: it cannot be empty and cannot contain whitespace. The valence must be a number between -4 and 4. Otherwise an `InvalidArgumentException` is thrown straight away, with a message that says what is wrong.

```php
(new Analyzer())->withWords(['very good' => 3.0]); // InvalidArgumentException (whitespace)
(new Analyzer())->withWords(['great' => 9]);       // InvalidArgumentException (outside -4..4)
```

Multi-word phrases are not supported. Add the words separately.

## Analyzers are immutable

Every `with*()` call returns a **new** analyzer and leaves the original unchanged, so it is safe to derive several analyzers from one base and to share them across your application:

```php
$base = new Analyzer();
$gaming = $base->withWords(['sick' => 2.0]);

$gaming->analyze('sick')->label; // Label::Positive

// $base still scores 'sick' with the stock valence.
$base->analyze('sick')->compound < $gaming->analyze('sick')->compound; // true
```

Build your customized analyzer once (a service container binding or a static property works well) and reuse it instead of rebuilding it for every text.

## Words that are also rules

Negations (`not`, `tidak`) and intensifiers (`very`, `sangat`) are part of the language rules, not the lexicon. Use `withWords()` for words that carry sentiment. Everything else, including emoticons such as `:)` and slang, is a normal lexicon entry.

Do not give a negation or an intensifier a valence with `withWords()`. As in VADER, the word then becomes a lexicon word and its behavior changes: a `very` with a valence no longer strengthens the word after it, and a `not` with a valence adds a score of its own. Leave these words out of `withWords()`.
