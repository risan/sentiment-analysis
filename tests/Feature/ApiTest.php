<?php

declare(strict_types=1);

use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Internal\RuleBook;
use Risan\Sentiment\Label;
use Risan\Sentiment\Language;
use Risan\Sentiment\Result;
use Risan\Sentiment\Sentiment;

describe('Sentiment::analyze', function () {
    it('analyzes English text by default', function () {
        $result = Sentiment::analyze('This package is awesome!');

        expect($result)->toBeInstanceOf(Result::class);
        expect($result->label)->toBe(Label::Positive);
        expect($result->isPositive())->toBeTrue();
        expect($result->isNegative())->toBeFalse();
        expect($result->isNeutral())->toBeFalse();
        expect($result->compound)->toBeGreaterThan(0.0);
    });

    it('accepts the language as an enum or as a string code', function () {
        expect(Sentiment::analyze('This is bad', Language::English)->label)->toBe(Label::Negative);
        expect(Sentiment::analyze('This is bad', 'en')->label)->toBe(Label::Negative);
    });

    it('rejects an unknown language code', function () {
        Sentiment::analyze('This is bad', 'xx');
    })->throws(ValueError::class);

    it('shares one set of rules between calls', function () {
        Sentiment::analyze('first');
        Sentiment::analyze('second');

        expect(RuleBook::for(Language::English))->toBe(RuleBook::for(Language::English));
    });

    it('cannot be instantiated', function () {
        expect((new ReflectionClass(Sentiment::class))->isInstantiable())->toBeFalse();
    });
});

describe('Result', function () {
    it('exposes the scores and a stable array and JSON shape', function () {
        $result = (new Analyzer())->analyze('VADER is smart, handsome, and funny.');

        expect(array_keys($result->toArray()))->toBe(['label', 'compound', 'positive', 'negative', 'neutral']);
        expect($result->toArray()['label'])->toBe($result->label->value);
        expect(json_encode($result))->toBe(json_encode($result->toArray()));
    });

    it('reports the three scores as shares that add up to one', function () {
        $result = (new Analyzer())->analyze('The food was great but the service was awful');

        expect($result->positive + $result->negative + $result->neutral)->toEqualWithDelta(1.0, 0.002);
    });
});

describe('labels and thresholds', function () {
    it('labels by the compound score against the threshold, boundary included', function () {
        // "good" scores 0.4404 and "bad" scores -0.5423 with the default lexicon.
        expect((new Analyzer(threshold: 0.4404))->analyze('good')->label)->toBe(Label::Positive);
        expect((new Analyzer(threshold: 0.4405))->analyze('good')->label)->toBe(Label::Neutral);
        expect((new Analyzer(threshold: 0.5423))->analyze('bad')->label)->toBe(Label::Negative);
        expect((new Analyzer(threshold: 0.5424))->analyze('bad')->label)->toBe(Label::Neutral);
    });

    it('uses 0.05 by default', function () {
        $analyzer = new Analyzer();

        expect($analyzer->withWords(['faint' => 0.1938])->analyze('faint')->compound)->toBe(0.05);
        expect($analyzer->withWords(['faint' => 0.1938])->analyze('faint')->label)->toBe(Label::Positive);
        expect($analyzer->withWords(['faint' => 0.19])->analyze('faint')->label)->toBe(Label::Neutral);
        expect($analyzer->withWords(['faint' => -0.1938])->analyze('faint')->label)->toBe(Label::Negative);
    });

    it('keeps empty and ordinary neutral text neutral at threshold zero', function () {
        $analyzer = new Analyzer(threshold: 0.0);

        expect($analyzer->analyze('')->label)->toBe(Label::Neutral);
        expect($analyzer->analyze('The table is in the kitchen.')->label)->toBe(Label::Neutral);
        expect($analyzer->analyze('good')->label)->toBe(Label::Positive);
        expect($analyzer->analyze('bad')->label)->toBe(Label::Negative);
    });

    it('rejects a threshold outside [0, 1)', function (float $threshold) {
        expect(fn() => new Analyzer(threshold: $threshold))->toThrow(InvalidArgumentException::class);
        expect(fn() => (new Analyzer())->withThreshold($threshold))->toThrow(InvalidArgumentException::class);
    })->with([
        'negative' => -0.1,
        'one' => 1.0,
        'above one' => 1.5,
        'NAN' => NAN,
        'INF' => INF,
    ]);

    it('accepts the edges of the allowed range', function (float $threshold) {
        expect((new Analyzer())->withThreshold($threshold))->toBeInstanceOf(Analyzer::class);
    })->with([
        'zero' => 0.0,
        'just below one' => 0.999,
    ]);
});

describe('empty input', function () {
    it('scores empty and whitespace-only text as all zeros and neutral', function (string $text) {
        expect((new Analyzer())->analyze($text)->toArray())->toBe([
            'label' => 'neutral',
            'compound' => 0.0,
            'positive' => 0.0,
            'negative' => 0.0,
            'neutral' => 0.0,
        ]);
    })->with(['', '   ', "\t\n", "\u{3000}"]);
});

describe('customizing the lexicon', function () {
    it('returns new instances and leaves the original unchanged', function () {
        $original = new Analyzer();
        $changed = $original->withWords(['cuan' => 2.5]);
        $removed = $original->withoutWords(['good']);
        $threshold = $original->withThreshold(0.5);

        expect($changed)->not->toBe($original);
        expect($removed)->not->toBe($original);
        expect($threshold)->not->toBe($original);
        expect($original->analyze('cuan')->compound)->toBe(0.0);
        expect($original->analyze('good')->compound)->toBe(0.4404);
        expect($original->analyze('good')->label)->toBe(Label::Positive);
        expect($changed->analyze('cuan')->label)->toBe(Label::Positive);
        expect($removed->analyze('good')->compound)->toBe(0.0);
        expect($threshold->analyze('good')->label)->toBe(Label::Neutral);
    });

    it('adds and overrides valences, case-insensitively', function () {
        $analyzer = (new Analyzer())->withWords(['CUAN' => 2.5, 'good' => -2, 'Zonk' => -2.0]);

        expect($analyzer->analyze('Cuan!')->isPositive())->toBeTrue();
        expect($analyzer->analyze('good')->isNegative())->toBeTrue();
        expect($analyzer->analyze('zonk')->isNegative())->toBeTrue();
    });

    it('overrides a numeric lexicon word given as a numeric string key', function () {
        $analyzer = (new Analyzer())->withWords(['1337' => -3]);

        expect($analyzer->analyze('1337')->isNegative())->toBeTrue();
        expect((new Analyzer())->analyze('1337')->isPositive())->toBeTrue();
    });

    it('removes words, case-insensitively', function () {
        $analyzer = (new Analyzer())->withoutWords(['KILL']);

        expect((new Analyzer())->analyze('kill')->isNegative())->toBeTrue();
        expect($analyzer->analyze('kill')->isNeutral())->toBeTrue();
    });

    it('rejects values outside -4..4 and non-numeric values', function (mixed $value) {
        expect(fn() => (new Analyzer())->withWords(['word' => $value]))->toThrow(InvalidArgumentException::class);
    })->with([
        'above 4' => 4.1,
        'below -4' => -4.1,
        'far above' => 100,
        'text' => 'high',
        'NAN' => NAN,
        'INF' => INF,
        'null' => null,
    ]);

    it('accepts the edges of the valence range', function () {
        expect((new Analyzer())->withWords(['top' => 4, 'bottom' => -4.0]))->toBeInstanceOf(Analyzer::class);
    });

    it('rejects empty keys and keys with whitespace, which could never match a token', function (string $key) {
        expect(fn() => (new Analyzer())->withWords([$key => 1.0]))->toThrow(InvalidArgumentException::class);
    })->with(['', 'two words', "tab\tkey", "line\nbreak", "\u{A0}"]);
});

describe('labels for sample texts', function () {
    it('gives the expected label', function (string $text, string $language, array $added, Label $expected) {
        $analyzer = (new Analyzer($language))->withWords($added);

        expect($analyzer->analyze($text)->label)->toBe($expected);
    })->with([
        'negated negative word' => ['Not bad at all!', 'en', [], Label::Positive],
        'Indonesian complaint' => ['Pelayanannya lambat dan mengecewakan.', 'id', [], Label::Negative],
        'Indonesian stock negative' => ['Filmnya zonk banget', 'id', [], Label::Negative],
        'Indonesian added negative word' => ['Filmnya bapuk banget', 'id', ['bapuk' => -2.0], Label::Negative],
        'Indonesian unknown word' => ['Investasinya cuan!', 'id', [], Label::Neutral],
    ]);

    it('lets an Indonesian booster after the word raise the compound', function () {
        expect(Sentiment::analyze('Bagus banget!', Language::Indonesian)->compound)
            ->toBeGreaterThan(Sentiment::analyze('Bagus!', Language::Indonesian)->compound);
    });
});

describe('hostile input', function () {
    it('never throws on invalid UTF-8 and drops the bad bytes without adding emphasis', function () {
        $analyzer = new Analyzer();

        expect($analyzer->analyze("good\xFF\xFE!"))->toEqual($analyzer->analyze('good!'));
        expect($analyzer->analyze("ok\xFF\xFE!"))->toEqual($analyzer->analyze('ok!'));
        expect($analyzer->analyze("\xFF\xFE")->isNeutral())->toBeTrue();
    });

    it('restores the substitute character it changes while cleaning invalid input', function () {
        mb_substitute_character(0x3F);
        $before = mb_substitute_character();

        (new Analyzer())->analyze("good\xFF");

        expect(mb_substitute_character())->toBe($before);
    });

    it('does not depend on the host mb_internal_encoding', function () {
        $analyzer = new Analyzer();
        $text = 'Café is GOOD but the 😊 was NOT great!!';
        $expected = $analyzer->analyze($text);
        $previous = mb_internal_encoding();

        mb_internal_encoding('ISO-8859-1');

        try {
            expect($analyzer->analyze($text))->toEqual($expected);
        } finally {
            mb_internal_encoding($previous);
        }
    });
});

describe('Analyzer', function () {
    it('reports its language', function () {
        expect((new Analyzer())->language())->toBe(Language::English);
        expect((new Analyzer('en'))->language())->toBe(Language::English);
    });

    it('keeps its language through with*() calls', function () {
        expect((new Analyzer('en'))->withThreshold(0.2)->withWords(['x' => 1])->withoutWords(['y'])->language())->toBe(Language::English);
    });
});
