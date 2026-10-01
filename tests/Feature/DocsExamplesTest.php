<?php

declare(strict_types=1);

/*
 * Mirrors the examples in website/src/content/docs, website/src/pages/index.astro
 * and README.md. Each test runs the calls shown there and asserts the values shown.
 * When you change an example or its output in one of those files, change it here too.
 */

use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Label;
use Risan\Sentiment\Language;
use Risan\Sentiment\Sentiment;

function averageCompound(Analyzer $analyzer, string $text): float
{
    $sentences = preg_split(
        '/(?<=[.!?])\s+/u',
        trim($text),
        -1,
        PREG_SPLIT_NO_EMPTY,
    );

    if ($sentences === false || $sentences === []) {
        return 0.0;
    }

    $total = 0.0;

    foreach ($sentences as $sentence) {
        $total += $analyzer->analyze($sentence)->compound;
    }

    return $total / count($sentences);
}

describe('homepage, README and quick start', function () {
    it('analyzes a text', function () {
        $result = Sentiment::analyze('This package is awesome!');

        expect($result->label)->toBe(Label::Positive);
        expect($result->compound)->toBe(0.6588);
    });

    it('reads the result', function () {
        $result = Sentiment::analyze('This package is awesome!');

        expect($result->positive)->toBe(0.594);
        expect($result->neutral)->toBe(0.406);
        expect($result->negative)->toBe(0.0);
        expect($result->isPositive())->toBeTrue();
        expect($result->isNegative())->toBeFalse();
        expect($result->isNeutral())->toBeFalse();
    });

    it('matches on the label', function () {
        $result = Sentiment::analyze('This package is awesome!');

        $message = match ($result->label) {
            Label::Positive => 'Glad you liked it!',
            Label::Negative => 'Sorry to hear that.',
            Label::Neutral => 'Thanks for the feedback.',
        };

        expect($message)->toBe('Glad you liked it!');
    });

    it('exports the result', function () {
        $result = Sentiment::analyze('This package is awesome!');

        expect($result->toArray())->toBe([
            'label' => 'positive',
            'compound' => 0.6588,
            'positive' => 0.594,
            'negative' => 0.0,
            'neutral' => 0.406,
        ]);
        expect(json_encode($result))
            ->toBe('{"label":"positive","compound":0.6588,"positive":0.594,"negative":0,"neutral":0.406}');
    });

    it('analyzes Indonesian by enum case or by code', function () {
        $result = Sentiment::analyze('Filmnya bagus banget!', Language::Indonesian);

        expect($result->label)->toBe(Label::Positive);
        expect($result->compound)->toBe(0.623);
        expect(Sentiment::analyze('Filmnya bagus banget!', 'id'))->toEqual($result);
    });

    it('throws a ValueError for an unknown language code', function () {
        Sentiment::analyze('Hello', 'fr');
    })->throws(ValueError::class);

    it('customizes an analyzer', function () {
        $analyzer = (new Analyzer(Language::Indonesian))
            ->withWords(['cuan' => 2.5])
            ->withoutWords(['kasar'])
            ->withThreshold(0.1);

        $result = $analyzer->analyze('Investasinya cuan!');

        expect($result->label)->toBe(Label::Positive);
        expect($result->compound)->toBe(0.5848);
    });

    it('scores the installation check', function () {
        expect(Sentiment::analyze('Installation was great!')->label->value)->toBe('positive');
    });
});

describe('introduction', function () {
    it('reads sarcasm at face value', function () {
        expect(Sentiment::analyze('Oh great, another delay.')->label)->toBe(Label::Positive);
    });
});

describe('understanding the scores', function () {
    it('shows the scores of the first example', function () {
        $result = Sentiment::analyze('VADER is smart, handsome, and funny.');

        expect($result->compound)->toBe(0.8316);
        expect($result->positive)->toBe(0.746);
        expect($result->neutral)->toBe(0.254);
        expect($result->negative)->toBe(0.0);
        expect($result->label)->toBe(Label::Positive);
    });

    it('shows how emphasis changes the compound score', function () {
        expect(Sentiment::analyze('not good')->compound)->toBe(-0.3412);
        expect(Sentiment::analyze('good')->compound)->toBe(0.4404);
        expect(Sentiment::analyze('very good')->compound)->toBe(0.4927);
        expect(Sentiment::analyze('VERY good!!!')->compound)->toBe(0.7005);
    });

    it('scores empty and whitespace-only text as zeros and neutral', function () {
        expect(Sentiment::analyze('')->toArray())->toBe([
            'label' => 'neutral',
            'compound' => 0.0,
            'positive' => 0.0,
            'negative' => 0.0,
            'neutral' => 0.0,
        ]);
    });
});

describe('languages', function () {
    it('analyzes English by default and Indonesian by enum or code', function () {
        expect(Sentiment::analyze('This is great!')->label)->toBe(Label::Positive);
        expect(Sentiment::analyze('Ini keren banget!', Language::Indonesian)->label)->toBe(Label::Positive);
        expect(Sentiment::analyze('Ini keren banget!', 'id')->label)->toBe(Label::Positive);
    });

    it('reports the language of an analyzer', function () {
        expect((new Analyzer('id'))->language())->toBe(Language::Indonesian);
    });

    it('scores the Indonesian examples', function () {
        $praise = Sentiment::analyze('Filmnya bagus banget!', 'id');
        $complaint = Sentiment::analyze('Pelayanannya lambat.', 'id');

        expect($praise->label)->toBe(Label::Positive);
        expect($praise->compound)->toBe(0.623);
        expect($complaint->label)->toBe(Label::Negative);
        expect($complaint->compound)->toBe(-0.4588);
    });

    it('lists the codes of both languages', function () {
        expect(Language::English->value)->toBe('en');
        expect(Language::Indonesian->value)->toBe('id');
    });
});

describe('customizing the lexicon', function () {
    it('overrides a word', function () {
        $text = 'That trick was sick!';
        $gaming = (new Analyzer())->withWords(['sick' => 2.0]);

        expect((new Analyzer())->analyze($text)->label)->toBe(Label::Negative);
        expect($gaming->analyze($text)->label)->toBe(Label::Positive);
    });

    it('adds a word', function () {
        $analyzer = (new Analyzer(Language::Indonesian))->withWords(['cuan' => 2.5, 'bapuk' => -2.0]);

        expect($analyzer->analyze('Investasinya cuan!')->isPositive())->toBeTrue();
    });

    it('removes a word', function () {
        $text = 'We will kill it at the launch.';
        $analyzer = (new Analyzer())->withoutWords(['kill']);

        expect((new Analyzer())->analyze($text)->label)->toBe(Label::Negative);
        expect($analyzer->analyze($text)->label)->toBe(Label::Neutral);
    });

    it('rejects a key with whitespace and a valence outside -4..4', function () {
        expect(fn() => (new Analyzer())->withWords(['very good' => 3.0]))->toThrow(InvalidArgumentException::class);
        expect(fn() => (new Analyzer())->withWords(['great' => 9]))->toThrow(InvalidArgumentException::class);
    });

    it('keeps the base analyzer unchanged', function () {
        $base = new Analyzer();
        $gaming = $base->withWords(['sick' => 2.0]);

        expect($gaming->analyze('sick')->label)->toBe(Label::Positive);
        expect($base->analyze('sick')->compound < $gaming->analyze('sick')->compound)->toBeTrue();
    });
});

describe('thresholds', function () {
    it('changes the label but not the scores', function () {
        $text = 'The movie was good.';
        $default = new Analyzer();
        $strict = $default->withThreshold(0.5);

        expect($default->analyze($text)->compound)->toBe(0.4404);
        expect($default->analyze($text)->label)->toBe(Label::Positive);
        expect($strict->analyze($text)->compound)->toBe(0.4404);
        expect($strict->analyze($text)->label)->toBe(Label::Neutral);
    });

    it('accepts a threshold in the constructor and with withThreshold()', function () {
        expect(new Analyzer(threshold: 0.1))->toBeInstanceOf(Analyzer::class);
        expect((new Analyzer())->withThreshold(0.4))->toBeInstanceOf(Analyzer::class);
    });

    it('rejects thresholds of 1 and above or below 0', function () {
        expect(fn() => (new Analyzer())->withThreshold(1.0))->toThrow(InvalidArgumentException::class);
        expect(fn() => (new Analyzer())->withThreshold(-0.1))->toThrow(InvalidArgumentException::class);
    });
});

describe('long text', function () {
    it('averages the sentences and drifts less than the whole text', function () {
        $review = 'The room was clean and the staff were lovely! '
            . 'The bed was comfortable. Breakfast was terrible.';

        expect(round(averageCompound(new Analyzer(), $review), 4))->toBe(0.2705);
        expect(Sentiment::analyze($review)->compound)->toBe(0.7901);
    });
});

describe('reference', function () {
    it('Sentiment: scores with the default analyzer', function () {
        expect(Sentiment::analyze('This package is awesome!')->label)->toBe(Label::Positive);
        expect(Sentiment::analyze('Filmnya bagus banget!', Language::Indonesian)->label)->toBe(Label::Positive);
        expect(fn() => Sentiment::analyze('Hello', 'xx'))->toThrow(ValueError::class);
    });

    it('Analyzer: builds the Indonesian example', function () {
        $analyzer = (new Analyzer(Language::Indonesian))
            ->withWords(['cuan' => 2.5, 'bapuk' => -2.0])
            ->withoutWords(['kasar'])
            ->withThreshold(0.1);

        $result = $analyzer->analyze('Investasinya cuan!');

        expect($result->label)->toBe(Label::Positive);
        expect($result->compound)->toBe(0.5848);
    });

    it('Analyzer: constructors and exceptions', function () {
        expect(new Analyzer())->toBeInstanceOf(Analyzer::class);
        expect(new Analyzer(Language::Indonesian))->toBeInstanceOf(Analyzer::class);
        expect(new Analyzer('en', threshold: 0.3))->toBeInstanceOf(Analyzer::class);
        expect(fn() => new Analyzer('xx'))->toThrow(ValueError::class);
        expect(fn() => new Analyzer(threshold: 1.0))->toThrow(InvalidArgumentException::class);
    });

    it('Analyzer: analyze()', function () {
        $result = (new Analyzer())->analyze('The food was not good.');

        expect($result->label)->toBe(Label::Negative);
        expect($result->compound)->toBe(-0.3412);
    });

    it('Analyzer: immutability', function () {
        $base = new Analyzer();
        $tuned = $base->withThreshold(0.3);

        expect($base === $tuned)->toBeFalse();
    });

    it('Result: the properties, methods and JSON', function () {
        $result = Sentiment::analyze('VADER is smart, handsome, and funny.');

        expect($result->label)->toBe(Label::Positive);
        expect($result->compound)->toBe(0.8316);
        expect($result->positive)->toBe(0.746);
        expect($result->negative)->toBe(0.0);
        expect($result->neutral)->toBe(0.254);
        expect($result->isPositive())->toBeTrue();
        expect($result->isNegative())->toBeFalse();
        expect($result->isNeutral())->toBeFalse();
        expect($result->toArray())->toBe([
            'label' => 'positive',
            'compound' => 0.8316,
            'positive' => 0.746,
            'negative' => 0.0,
            'neutral' => 0.254,
        ]);
        expect(json_encode($result))
            ->toBe('{"label":"positive","compound":0.8316,"positive":0.746,"negative":0,"neutral":0.254}');
    });

    it('Label: the native enum methods', function () {
        $result = Sentiment::analyze('This package is awesome!');

        expect($result->label === Label::Positive)->toBeTrue();
        expect($result->label->value)->toBe('positive');
        expect($result->label->name)->toBe('Positive');
        expect(Label::from('negative'))->toBe(Label::Negative);
        expect(Label::tryFrom('mixed'))->toBeNull();
        expect(Label::cases())->toBe([Label::Positive, Label::Negative, Label::Neutral]);
        expect(fn() => Label::from('mixed'))->toThrow(ValueError::class);
    });

    it('Language: the native enum methods', function () {
        expect(Language::from('id'))->toBe(Language::Indonesian);
        expect(Language::tryFrom('fr'))->toBeNull();
        expect(Language::English->value)->toBe('en');
        expect(Language::cases())->toBe([Language::English, Language::Indonesian]);
        expect(fn() => Language::from('fr'))->toThrow(ValueError::class);
        expect(fn() => Sentiment::analyze('Bonjour', 'fr'))->toThrow(ValueError::class);
    });
});

describe('upgrading from v1', function () {
    it('shows the v2 values', function () {
        $result = Sentiment::analyze('This package is awesome!');

        expect($result->label->value)->toBe('positive');
        expect($result->toArray())->toBe([
            'label' => 'positive',
            'compound' => 0.6588,
            'positive' => 0.594,
            'negative' => 0.0,
            'neutral' => 0.406,
        ]);
    });

    it('builds the custom words example', function () {
        expect((new Analyzer())->withWords(['sick' => 2.0])->withoutWords(['kill']))->toBeInstanceOf(Analyzer::class);
    });
});
