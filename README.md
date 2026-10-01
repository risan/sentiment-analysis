# Sentiment Analysis for PHP

Scores English and Indonesian text as positive, negative or neutral. Pure PHP 8.3+, no API calls, no dependencies.

```php
use Risan\Sentiment\Sentiment;

$result = Sentiment::analyze(
    'This package is awesome!',
);

$result->label;    // Label::Positive
$result->compound; // 0.6588
```

Documentation and live examples: <https://sentiment-analysis.risanb.com>

## Install

```bash
composer require risan/sentiment-analysis
```

Requires PHP 8.3 or newer and the `mbstring` extension.

## Usage

### Analyze a text

Pass a string. You get a result with a label and a compound score.

```php
use Risan\Sentiment\Sentiment;

$result = Sentiment::analyze(
    'This package is awesome!',
);

$result->label;    // Label::Positive
$result->compound; // 0.6588
```

`compound` runs from -1 (very negative) to +1 (very positive). The label comes from `compound`.

### Read the result

The three shares add up to about 1. Three methods check the label.

```php
$result->positive; // 0.594
$result->neutral;  // 0.406
$result->negative; // 0.0

$result->isPositive(); // true
$result->isNegative(); // false
$result->isNeutral();  // false
```

### Export the result

Turn the result into an array, or encode it as JSON.

```php
$result->toArray();
// ['label' => 'positive', 'compound' => 0.6588,
//  'positive' => 0.594, 'negative' => 0.0,
//  'neutral' => 0.406]

json_encode($result);
// {"label":"positive","compound":0.6588,
//  "positive":0.594,"negative":0,
//  "neutral":0.406}
```

### Analyze Indonesian

Pass the language as an enum case, or as the code `'id'`. An unknown code throws a `ValueError`.

```php
use Risan\Sentiment\Language;
use Risan\Sentiment\Sentiment;

$result = Sentiment::analyze(
    'Filmnya bagus banget!',
    Language::Indonesian,
);

$result->label;    // Label::Positive
$result->compound; // 0.623

Sentiment::analyze('Filmnya bagus banget!', 'id');
```

### Customize the analyzer

Add or remove words and change the threshold. Every `with*()` call returns a new analyzer.

```php
use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Language;

$analyzer = (new Analyzer(Language::Indonesian))
    ->withWords(['cuan' => 2.5])
    ->withoutWords(['kasar'])
    ->withThreshold(0.1);

$result = $analyzer->analyze('Investasinya cuan!');

$result->label;    // Label::Positive
$result->compound; // 0.5848
```

The default threshold is 0.05. A text is positive when `compound` is at least the threshold, negative when it is at most minus the threshold, and neutral otherwise.

More in the documentation: [understanding the scores](https://sentiment-analysis.risanb.com/guides/understanding-scores/), [languages](https://sentiment-analysis.risanb.com/guides/languages/), [customizing the lexicon](https://sentiment-analysis.risanb.com/guides/customizing-the-lexicon/), [thresholds](https://sentiment-analysis.risanb.com/guides/thresholds/) and [long text](https://sentiment-analysis.risanb.com/guides/long-text/).

## How it works

The engine is a PHP version of [VADER](https://github.com/cjhutto/vaderSentiment) (Hutto & Gilbert, 2014).

1. A lexicon gives each known word a score from -4 to +4.
2. Rules adjust the scores in context: negation, intensifiers and dampeners ("very", "kind of"), the contrast word "but", ALL CAPS, `!` and `?`, emoticons and emoji.
3. The package adds up the scores and squashes the sum into `compound`, between -1 and +1.

English uses the original VADER lexicon and rules. Its scores match `vaderSentiment` 3.3.2 on more than 2,700 reference scores, with two deliberate fixes. Indonesian runs the same engine with a lexicon and rules written for this project. It handles negation, intensifiers before and after the word (`bagus banget`), contrast words (`tapi`), informal spellings and emoji.

It is a lexicon method. It does not understand sarcasm, regional languages or words from your own field. [How it works, in the docs](https://sentiment-analysis.risanb.com/getting-started/introduction/).

## Accuracy and performance

On the test split of [IndoNLU SmSA](https://github.com/IndoNLP/indonlu) (500 reviews and comments, three classes, default threshold), Indonesian scoring reaches 81.2% accuracy and 0.744 macro F1. Always guessing the most common class gives 41.6% accuracy. A fine-tuned model reaches more. Reproduce the numbers with `php tools/evaluate-id.php --split=test`. See [Languages](https://sentiment-analysis.risanb.com/guides/languages/).

The package scores about 80,000 short English texts per second and about 14,000 reviews per second, with OPcache on (PHP 8.5, a laptop with Docker on WSL2, single runs that vary by 10% or more). The whole benchmark process peaks at about 2 MB. Run `php benchmarks/run.php` on your own hardware. See [Performance](https://sentiment-analysis.risanb.com/guides/performance/).

## Development

```bash
composer install
composer test          # Pest
composer lint          # Pint (PER style), check only
composer analyse       # PHPStan, level max
composer bench         # throughput and memory
composer build:lexicons # regenerate resources/ from tools/data/
```

`resources/` is generated from `tools/data/`. Run `composer build:lexicons` after you edit the sources. A test fails when they drift apart.

The examples in this README, on the website and in the docs are checked by `tests/Feature/DocsExamplesTest.php`.

## Credits and license

MIT, see [LICENSE.md](LICENSE.md). Third-party notices, including VADER's license and citation, are in [NOTICE.md](NOTICE.md).
