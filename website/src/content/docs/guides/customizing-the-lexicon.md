---
title: Customizing the lexicon
description: Add, override or remove words with Analyzer::withWords() and withoutWords() to adapt sentiment scores to your domain, slang or brand vocabulary.
---

The lexicon is the word list. Each word has a score. The default scores suit general text, but your field may disagree. In a gaming forum, `sick` is praise. Use an [`Analyzer`](/reference/analyzer/) to change the list.

```php
use Risan\Sentiment\Analyzer;

$text = 'That trick was sick!';

(new Analyzer())->analyze($text)->label;
// Label::Negative

$gaming = (new Analyzer())->withWords(['sick' => 2.0]);

$gaming->analyze($text)->label;
// Label::Positive
```

## Add or override words

`withWords()` takes an array of `word => score`. A new word is added. An existing word gets the new score.

```php
use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Language;

$analyzer = (new Analyzer(Language::Indonesian))
    ->withWords(['cuan' => 2.5, 'bapuk' => -2.0]);

$analyzer->analyze('Investasinya cuan!')->isPositive(); // true
```

The package lower-cases your keys. It also ignores the case of the text. So `cuan`, `Cuan` and `CUAN` all match the same entry. A score can be an int or a float.

## Choose a score

A score runs from **-4** (extremely negative) to **+4** (extremely positive). As a rough guide:

| Score | Feels like |
|---|---|
| ±0.5 | faint |
| ±1.5 | mild |
| ±2.5 | clear |
| ±3.5 | extreme |

Compare with words the lexicon already knows. If `good` is about 1.9 and `great` is about 3.1, a word that means "better than good, not quite great" belongs near 2.5.

## Remove words

`withoutWords()` takes a list of words to drop. They then count as plain neutral words.

```php
use Risan\Sentiment\Analyzer;

$text = 'We will kill it at the launch.';

(new Analyzer())->analyze($text)->label;
// Label::Negative

$analyzer = (new Analyzer())->withoutWords(['kill']);

$analyzer->analyze($text)->label;
// Label::Neutral
```

## Rules for words

A key must be one word. It cannot be empty and it cannot contain whitespace. The score must be a number from -4 to 4. Otherwise the package throws an `InvalidArgumentException` at once, with a message that says what is wrong.

```php
use Risan\Sentiment\Analyzer;

(new Analyzer())->withWords(['very good' => 3.0]);
// InvalidArgumentException (whitespace)

(new Analyzer())->withWords(['great' => 9]);
// InvalidArgumentException (outside -4..4)
```

Phrases with more than one word are not supported. Add the words one by one.

## Analyzers are immutable

Every `with*()` call returns a **new** analyzer. The original stays the same. You can derive several analyzers from one base and share them across your application.

```php
use Risan\Sentiment\Analyzer;

$base = new Analyzer();
$gaming = $base->withWords(['sick' => 2.0]);

$gaming->analyze('sick')->label;
// Label::Positive

// $base still uses the default score for 'sick'.
$base->analyze('sick')->compound < $gaming->analyze('sick')->compound;
// true
```

Build your custom analyzer once and reuse it. A service container binding or a static property works well. Do not rebuild it for every text.

## Words that are also rules

Negations (`not`, `tidak`) and intensifiers (`very`, `sangat`) belong to the language rules, not to the lexicon. Use `withWords()` for words that carry sentiment. Emoticons such as `:)` and slang are normal lexicon entries.

Do not give a negation or an intensifier a score with `withWords()`. As in VADER, the word then becomes a lexicon word and acts differently. A `very` with a score no longer strengthens the next word. A `not` with a score adds a score of its own.
