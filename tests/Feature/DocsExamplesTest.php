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

describe('homepage and README usage blocks', function () {
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

    it('customizes an analyzer', function () {
        $analyzer = (new Analyzer(Language::Indonesian))
            ->withWords(['cuan' => 2.5])
            ->withoutWords(['kasar'])
            ->withThreshold(0.1);

        $result = $analyzer->analyze('Investasinya cuan!');

        expect($result->label)->toBe(Label::Positive);
        expect($result->compound)->toBe(0.5848);
    });
});
