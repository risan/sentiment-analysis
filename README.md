# Sentiment Analysis

Fast, dependency-free sentiment analysis for PHP. English and Indonesian, no API calls, no
training: it scores text with a lexicon and a small set of rules (negation, intensifiers,
contrast, ALL CAPS, punctuation, emoticons and emoji).

```php
use Risan\Sentiment\Sentiment;

$result = Sentiment::analyze('This package is awesome!');

$result->label;    // Label::Positive
$result->compound; // 0.6588
```

Documentation and live examples: <https://sentiment-analysis.risanb.com>

## Features

- English scoring that matches [VADER](https://github.com/cjhutto/vaderSentiment) 3.3.2
  (checked against more than 2,700 reference scores).
- Indonesian support: negation, boosters before and after the word (`bagus banget`), contrast
  words (`tapi`), informal spellings, emoji.
- No runtime dependencies besides `ext-mbstring`. No network calls.
- Fast and small: tens of thousands of short texts per second, a few MB of memory.
- A small, immutable API with typed results.

## Installation

```bash
composer require risan/sentiment-analysis
```

Requires PHP 8.3 or newer and `ext-mbstring`.

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Risan\Sentiment\Language;
use Risan\Sentiment\Sentiment;

$english = Sentiment::analyze('This package is awesome!');
$indonesian = Sentiment::analyze('Filmnya bagus banget!', Language::Indonesian);

echo $english->label->value;    // positive
echo $indonesian->label->value; // positive
echo json_encode($english);     // {"label":"positive","compound":0.6588,"positive":0.594,"negative":0,"neutral":0.406}
```

The language can also be given as a code: `Sentiment::analyze('Filmnya bagus banget!', 'id')`.
An unknown code throws a `ValueError`.

### Reading the result

| Property | Meaning |
| --- | --- |
| `compound` | Overall score from -1 (most negative) to 1 (most positive), 4 decimals. |
| `positive`, `negative`, `neutral` | Share of the text that is positive, negative or neutral, 3 decimals. |
| `label` | `Label::Positive`, `Label::Negative` or `Label::Neutral`, from `compound` and the threshold. |

`isPositive()`, `isNegative()`, `isNeutral()` and `toArray()` are available too; a `Result` is
`JsonSerializable`.

## Customizing

`Analyzer` is immutable: every `with*()` method returns a new instance.

```php
use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Language;

$analyzer = (new Analyzer(Language::Indonesian))
    ->withWords(['cuan' => 2.5, 'bapuk' => -2.0]) // add or override valences (-4..4)
    ->withoutWords(['kasar'])                      // drop words from the lexicon
    ->withThreshold(0.1);                          // neutral band is (-0.1, 0.1)

$analyzer->analyze('Filmnya bapuk banget')->label; // Label::Negative
```

The default threshold is 0.05, VADER's published value. A text is `Positive` when
`compound >= threshold`, `Negative` when `compound <= -threshold`, and `Neutral` otherwise.

### Long text

The scoring works best on a sentence at a time. For a longer text, score each sentence and
average:

```php
$sentences = preg_split('/(?<=[.!?])\s+/u', $review, -1, PREG_SPLIT_NO_EMPTY);
$scores = array_map(fn (string $sentence): float => Sentiment::analyze($sentence)->compound, $sentences);
$average = array_sum($scores) / count($scores);
```

## How it works

The engine is a PHP port of VADER's rules (Hutto & Gilbert, 2014): a lexicon of word valences,
adjusted for negation within three words, intensifiers and dampeners ("very", "kind of"), the
contrast word "but", ALL CAPS, exclamation and question marks, and emoticons and emoji.
Indonesian runs the same engine with Indonesian data: its own lexicon, negations, boosters
before and after the word, and contrast words.

Two deliberate differences from the VADER reference code, both bug fixes: the weighting around
"but" is applied by position (the reference mis-scales repeated valences), and the emoji
variation selector (U+FE0F) is ignored so that "❤️" scores like "❤". Invalid UTF-8 never throws:
the bad bytes are dropped.

The Indonesian lexicon was written for this package. It does not use any existing Indonesian
sentiment lexicon. It is a lexicon method: it does not understand sarcasm, regional languages or
domain-specific words, and accuracy is below what a fine-tuned model reaches. On the test split
of [IndoNLU SmSA](https://github.com/IndoNLP/indonlu) (500 reviews and comments, three classes,
default threshold) it reaches 81.2% accuracy and 0.744 macro F1; always guessing the most common
class gives 41.6% accuracy. Reproduce it with `php tools/evaluate-id.php --split=test`. More
detail is in the documentation.

## Performance

Measured with `php benchmarks/run.php` on PHP 8.5 (CLI, Docker on a Windows laptop with WSL2, so
treat the numbers as a rough guide), OPcache on and off:

| Text | Words | Analyses per second, OPcache on | Analyses per second, OPcache off |
| --- | --- | --- | --- |
| Tweet, English | 17 | about 66,000 | about 56,000 |
| Review, English | 106 | about 12,000 | about 9,800 |
| Long text, English | 2,120 | about 650 | about 530 |

The lexicons are plain PHP array files. With OPcache they live in shared memory and cost almost
nothing per process; without it they load once per process (about 1 MB for English). The peak
memory of the whole benchmark process (both languages) is about 3 MB.

## Development

```bash
composer install
composer test          # Pest
composer lint          # Pint (PER style), check only
composer analyse       # PHPStan, level max
composer bench         # throughput and memory
composer build:lexicons # regenerate resources/ from tools/data/
```

`resources/` is generated from `tools/data/`; `composer build:lexicons` must be re-run after
editing the sources, and a test fails when they drift apart.

## Credits and license

MIT, see [LICENSE.md](LICENSE.md). Third-party notices, including VADER's license and citation,
are in [NOTICE.md](NOTICE.md).
