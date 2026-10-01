<?php

declare(strict_types=1);

use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Label;

function englishCompound(string $text): float
{
    return (new Analyzer())->analyze($text)->compound;
}

it('reproduces the published VADER README scores', function () {
    $smart = (new Analyzer())->analyze('VADER is smart, handsome, and funny.');

    expect($smart->compound)->toBe(0.8316);
    expect($smart->positive)->toBe(0.746);
    expect($smart->neutral)->toBe(0.254);
    expect($smart->negative)->toBe(0.0);
    expect(englishCompound('VADER is not smart, handsome, nor funny.'))->toBe(-0.7424);
});

it('orders texts by strength: negation, plain, booster, caps and exclamation marks', function () {
    $ladder = [
        'not good',
        'good',
        'very good',
        'VERY good',
        'VERY good!!!',
    ];

    $compounds = array_map(englishCompound(...), $ladder);

    expect($compounds[0])->toBeLessThan(0.0);
    expect($compounds[1])->toBeGreaterThan(0.0);

    for ($i = 1; $i < count($compounds); $i++) {
        expect($compounds[$i])->toBeGreaterThan($compounds[$i - 1], "{$ladder[$i]} should beat {$ladder[$i - 1]}");
    }
});

it('reads a dampener as weaker than the plain word', function (string $dampened, string $plain) {
    expect(abs(englishCompound($dampened)))->toBeLessThan(abs(englishCompound($plain)));
})->with([
    'kind of' => ['It was kind of good', 'It was good'],
    'sort of' => ['It was sort of good', 'It was good'],
    'slightly' => ['slightly good', 'good'],
]);

it('lets the clause after "but" dominate', function () {
    expect(englishCompound('The food was great but the service was awful'))->toBeLessThan(0.0);
    expect(englishCompound('The food was awful but the service was great'))->toBeGreaterThan(0.0);
});

it('scales "but" by position, not by value, when valences relate', function () {
    // The reference finds positions with list.index(value): the scaled "abhor"
    // (-2.0 * 0.5 = -1.0) is then mistaken for "aches" (-1.0), which is left unscaled.
    expect(englishCompound('abhor but aches'))->toBe(-0.5423);
});

it('treats "no" as a negation of the next lexicon word', function () {
    expect(englishCompound('no good'))->toBeLessThan(0.0);
    expect(englishCompound('no problem'))->toBeGreaterThan(0.0);
});

it('handles "least" as negation only when it is not "at least" or "very least"', function () {
    expect(englishCompound('the least good option'))->toBeLessThan(0.0);
    expect(englishCompound('at least good'))->toBeGreaterThan(0.0);
});

it('lets "never so good" amplify instead of negate, unlike "never good"', function () {
    expect(englishCompound('never good'))->toBeLessThan(0.0);
    expect(englishCompound('never so good'))->toBeGreaterThan(englishCompound('so good'));
});

it('reads known idioms by their own valence', function () {
    expect(englishCompound('this is the shit'))->toBeGreaterThan(0.0);
    expect(englishCompound('this is shit'))->toBeLessThan(0.0);
});

it('emphasises with ALL CAPS only when other words are not capitalised', function () {
    expect(englishCompound('GOOD movie'))->toBeGreaterThan(englishCompound('good movie'));
    expect(englishCompound('GOOD MOVIE'))->toBe(englishCompound('good movie'));
});

it('emphasises with exclamation marks up to four and question marks from two', function () {
    expect(englishCompound('good!'))->toBeGreaterThan(englishCompound('good'));
    expect(englishCompound('good!!!!!'))->toBe(englishCompound('good!!!!'));
    expect(englishCompound('good?'))->toBe(englishCompound('good'));
    expect(englishCompound('good??'))->toBeGreaterThan(englishCompound('good'));
});

it('scores emoticons and emoji', function () {
    expect(englishCompound(':)'))->toBeGreaterThan(0.0);
    expect(englishCompound(':('))->toBeLessThan(0.0);
    expect(englishCompound('😍'))->toBeGreaterThan(0.0);
    expect(englishCompound('😭'))->toBeLessThan(0.0);
});

it('scores an emoji with a variation selector like the bare emoji', function () {
    expect(englishCompound('❤️'))->toBe(englishCompound('❤'));
    expect(englishCompound('I ❤️ this'))->toBe(englishCompound('I ❤ this'));
});

it('counts punctuation that comes from an emoji description', function () {
    expect(englishCompound('🔛'))->toBe(englishCompound('ON! arrow'));
});

it('splits on every whitespace character Python knows', function (string $separator) {
    expect(englishCompound("not{$separator}good"))->toBe(englishCompound('not good'));
})->with([
    'tab' => ["\t"],
    'newline' => ["\n"],
    'file separator' => ["\x1C"],
    'next line' => ["\u{85}"],
    'no-break space' => ["\u{A0}"],
    'ideographic space' => ["\u{3000}"],
]);

it('keeps characters Python does not treat as whitespace inside the token', function () {
    expect(englishCompound("not\u{180E}good"))->toBe(0.0);
    expect(englishCompound("good\0"))->toBe(0.0);
});

it('labels the neutral label for text without lexicon words', function () {
    expect((new Analyzer())->analyze('The table is in the kitchen.')->label)->toBe(Label::Neutral);
});
