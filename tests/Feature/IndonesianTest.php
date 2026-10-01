<?php

declare(strict_types=1);

use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Label;
use Risan\Sentiment\Language;
use Risan\Sentiment\Sentiment;

/**
 * The rule tests pin their own valences, so they do not move when the shipped lexicon is tuned.
 */
function indonesianAnalyzer(): Analyzer
{
    return (new Analyzer(Language::Indonesian))->withWords([
        'bagus' => 2.0,
        'jelek' => -2.0,
        'keren' => 2.5,
        'parah' => -2.5,
        'lumayan' => 1.0,
        'sayang' => 2.0,
    ]);
}

function indonesianCompound(string $text): float
{
    return indonesianAnalyzer()->analyze($text)->compound;
}

describe('language selection', function () {
    it('analyzes Indonesian by enum and by string code', function () {
        expect(Sentiment::analyze('Filmnya bagus banget!', Language::Indonesian)->label)->toBe(Label::Positive);
        expect(Sentiment::analyze('Filmnya jelek banget!', 'id')->label)->toBe(Label::Negative);
        expect((new Analyzer('id'))->language())->toBe(Language::Indonesian);
    });
});

describe('negation', function () {
    it('flips a sentiment word', function (string $negation) {
        expect(indonesianCompound("{$negation} bagus"))->toBeLessThan(0.0);
        expect(indonesianCompound("{$negation} jelek"))->toBeGreaterThan(0.0);
    })->with([
        'tidak', 'tak', 'bukan', 'belum', 'jangan', 'tanpa', 'kurang',
        'ga', 'gak', 'nggak', 'enggak', 'engga', 'ngga', 'gk', 'tdk', 'ndak', 'nda', 'kagak', 'blm', 'jgn', 'bkn',
    ]);

    it('reaches over up to two words in between', function () {
        expect(indonesianCompound('tidak film bagus'))->toBeLessThan(0.0);
        expect(indonesianCompound('tidak film yang bagus'))->toBeLessThan(0.0);
        expect(indonesianCompound('tidak film yang sangat bagus'))->toBeGreaterThan(0.0);
    });

    it('does not use the English-only rules', function () {
        expect(indonesianCompound("don't bagus"))->toBe(indonesianCompound('bagus'));
        expect(indonesianCompound('no bagus'))->toBe(indonesianCompound('bagus'));
    });
});

describe('boosters before the word', function () {
    it('strengthens the word', function (string $booster) {
        expect(indonesianCompound("{$booster} bagus"))->toBeGreaterThan(indonesianCompound('bagus'));
        expect(indonesianCompound("{$booster} jelek"))->toBeLessThan(indonesianCompound('jelek'));
    })->with([
        'sangat', 'amat', 'paling', 'terlalu', 'sungguh', 'begitu', 'sangatlah', 'makin', 'semakin', 'lebih',
        'super', 'benar-benar', 'bener-bener', 'bnr2', 'sgt',
    ]);

    it('weakens the word', function (string $dampener) {
        expect(indonesianCompound("{$dampener} bagus"))->toBeLessThan(indonesianCompound('bagus'));
        expect(indonesianCompound("{$dampener} jelek"))->toBeGreaterThan(indonesianCompound('jelek'));
    })->with(['agak', 'cukup', 'sedikit', 'rada', 'setengah']);

    it('boosts at the start of a sentence too', function () {
        expect(indonesianCompound('Lebih bagus'))->toBeGreaterThan(indonesianCompound('bagus'));
    });
});

describe('boosters after the word', function () {
    it('strengthens the word', function (string $booster) {
        expect(indonesianCompound("bagus {$booster}"))->toBeGreaterThan(indonesianCompound('bagus'));
        expect(indonesianCompound("jelek {$booster}"))->toBeLessThan(indonesianCompound('jelek'));
    })->with(['banget', 'bgt', 'sekali', 'bener', 'amat', 'abis']);

    it('counts a booster two positions after the word for less', function () {
        $adjacent = indonesianCompound('bagus banget');
        $oneBetween = indonesianCompound('bagus film banget');

        expect($oneBetween)->toBeGreaterThan(indonesianCompound('bagus'));
        expect($oneBetween)->toBeLessThan($adjacent);
    });

    it('does not reach three positions', function () {
        expect(indonesianCompound('bagus film yang banget'))->toBe(indonesianCompound('bagus'));
    });

    it('applies the booster before the negation, like a booster in front of the word', function () {
        expect(indonesianCompound('tidak bagus banget'))->toBeLessThan(indonesianCompound('tidak bagus'));
    });

    it('is emphasised by ALL CAPS like a pre-booster', function () {
        expect(indonesianCompound('bagus BANGET'))->toBeGreaterThan(indonesianCompound('bagus banget'));
    });
});

describe('contrast words', function () {
    it('lets the clause after the contrast word dominate', function (string $contrast) {
        expect(indonesianCompound("film jelek {$contrast} keren"))->toBeGreaterThan(0.0);
        expect(indonesianCompound("film keren {$contrast} jelek"))->toBeLessThan(0.0);
    })->with(['tapi', 'tetapi', 'tp', 'namun', 'sayangnya']);

    it('does not treat padahal as a contrast word', function () {
        expect(indonesianCompound('film jelek padahal keren'))->toBe(indonesianCompound('film jelek dan keren'));
        expect(indonesianCompound('film jelek tapi keren'))->toBeGreaterThan(indonesianCompound('film jelek dan keren'));
    });

    it('gives no valence of its own to sayangnya, even though sayang is a lexicon word', function () {
        expect(indonesianCompound('sayangnya'))->toBe(0.0);
        expect(indonesianCompound('film sayangnya'))->toBe(0.0);
        expect(indonesianCompound('sayang'))->toBeGreaterThan(0.0);
    });
});

describe('phrase modifiers', function () {
    it('reads "kurang lebih" as one dampener, not as a negation and a booster', function () {
        $plain = indonesianCompound('bagus');
        $phrase = indonesianCompound('kurang lebih bagus');

        expect($phrase)->toBeGreaterThan(0.0);
        expect($phrase)->toBeLessThan($plain);
        expect(indonesianCompound('Kurang Lebih bagus'))->toBe($phrase);
        expect(indonesianCompound('harganya kurang lebih bagus'))->toBe($phrase);
    });

    it('keeps "kurang" and "lebih" meaning what they mean on their own', function () {
        expect(indonesianCompound('kurang bagus'))->toBeLessThan(0.0);
        expect(indonesianCompound('lebih bagus'))->toBeGreaterThan(indonesianCompound('bagus'));
        expect(indonesianCompound('kurang bagus lebih'))->toBeLessThan(0.0);
    });
});

describe('words that are both sentiment words and modifiers', function () {
    it('reads parah as a negative word when no sentiment word precedes it', function () {
        expect(indonesianCompound('filmnya parah'))->toBeLessThan(0.0);
        expect(indonesianCompound('parah'))->toBeLessThan(0.0);
    });

    it('reads parah after a sentiment word as a booster without valence of its own', function () {
        expect(indonesianCompound('keren parah'))->toBeGreaterThan(indonesianCompound('keren'));
        expect(indonesianCompound('keren parah'))->toBe(indonesianCompound('keren banget'));
        expect(indonesianCompound('jelek parah'))->toBeLessThan(indonesianCompound('jelek'));
    });

    it('reads lumayan as a mildly positive word on its own', function () {
        expect(indonesianCompound('filmnya lumayan'))->toBeGreaterThan(0.0);
        expect(indonesianCompound('filmnya lumayan'))->toBeLessThan(indonesianCompound('filmnya bagus'));
    });

    it('reads lumayan before a sentiment word as a dampener', function () {
        $dampened = indonesianCompound('lumayan bagus');

        expect($dampened)->toBeGreaterThan(0.0);
        expect($dampened)->toBeLessThan(indonesianCompound('bagus'));
        expect($dampened)->toBe(indonesianCompound('agak bagus'));
    });

    it('lets the pre-modifier win when both words could modify each other', function () {
        $dampenedParah = indonesianCompound('lumayan parah');

        expect($dampenedParah)->toBeLessThan(0.0);
        expect($dampenedParah)->toBeGreaterThan(indonesianCompound('parah'));
        expect($dampenedParah)->toBe(indonesianCompound('agak parah'));
    });

    it('keeps the first lumayan as a word when another lumayan follows', function () {
        expect(indonesianCompound('lumayan lumayan bagus'))->toBeGreaterThan(indonesianCompound('lumayan bagus'));
    });
});

describe('suffixes and reduplication', function () {
    it('looks a word up without its clitic suffix', function (string $suffix) {
        expect(indonesianCompound("bagus{$suffix}"))->toBe(indonesianCompound('bagus'));
    })->with(['nya', 'lah', 'kah', 'pun']);

    it('looks a reduplicated word up by its base', function (string $word) {
        expect(indonesianCompound($word))->toBe(indonesianCompound('bagus'));
    })->with(['bagus-bagus', 'bagus2']);

    it('ignores a suffix when the rest is not a sentiment word', function () {
        expect(indonesianCompound('filmnya'))->toBe(0.0);
    });
});

describe('emoji and emoticons', function () {
    it('scores emoji in Indonesian sentences by their description', function () {
        expect(indonesianCompound('filmnya 😍'))->toBeGreaterThan(0.0);
        expect(indonesianCompound('filmnya 😭'))->toBeLessThan(0.0);
        expect(indonesianCompound('filmnya 😊'))->toBeGreaterThan(indonesianCompound('filmnya'));
    });

    it('scores an emoji with a variation selector like the bare emoji', function () {
        expect(indonesianCompound('filmnya ❤️'))->toBe(indonesianCompound('filmnya ❤'));
        expect(indonesianCompound('filmnya ❤️'))->toBeGreaterThan(0.0);
    });

    it('scores emoticons', function () {
        expect(indonesianCompound('filmnya :)'))->toBeGreaterThan(0.0);
        expect(indonesianCompound('filmnya :('))->toBeLessThan(0.0);
    });

    it('does not let the English word "no" from an emoji description leak in as Indonesian', function () {
        expect((new Analyzer('id'))->analyze('no 7')->isNeutral())->toBeTrue();
    });
});

describe('emphasis', function () {
    it('counts exclamation marks and ALL CAPS like English', function () {
        expect(indonesianCompound('bagus!!!'))->toBeGreaterThan(indonesianCompound('bagus'));
        expect(indonesianCompound('BAGUS film'))->toBeGreaterThan(indonesianCompound('bagus film'));
    });
});

function shippedCompound(string $text): float
{
    return Sentiment::analyze($text, Language::Indonesian)->compound;
}

describe('the shipped lexicon on everyday sentences', function () {
    it('labels the sentence', function (string $text, Label $expected) {
        expect(Sentiment::analyze($text, Language::Indonesian)->label)->toBe($expected);
    })->with([
        'food and service' => ['Makanannya enak sekali dan pelayanannya ramah.', Label::Positive],
        'place and price' => ['Tempatnya nyaman, bersih, dan harganya murah.', Label::Positive],
        'happy with a gift' => ['Aku senang banget sama hadiah ini!', Label::Positive],
        'scenery' => ['Pemandangannya indah dan udaranya sejuk.', Label::Positive],
        'online shop' => ['Barang sampai dengan cepat, sesuai deskripsi, penjual ramah.', Label::Positive],
        'bank' => ['Pelayanan bank ini cepat dan stafnya sopan.', Label::Positive],
        'bad service' => ['Pelayanannya buruk dan makanannya tidak enak.', Label::Negative],
        'late order' => ['Aku kecewa, pesanannya datang terlambat.', Label::Negative],
        'dirty room' => ['Kamarnya kotor dan bau.', Label::Negative],
        'slow app' => ['Aplikasinya lemot dan sering error.', Label::Negative],
        'contrast, the second clause wins' => ['Hotelnya lumayan, tapi kamarnya kotor.', Label::Negative],
        'plain facts' => ['Besok rapat jam 3 sore di kantor.', Label::Neutral],
        'opening hours' => ['Restoran ini buka dari pagi sampai malam.', Label::Neutral],
    ]);

    it('does not score ordinary words that only look like sentiment words', function (string $text) {
        expect(Sentiment::analyze($text, Language::Indonesian)->isNeutral())->toBeTrue();
    })->with([
        '"salah satu" means "one of"' => 'salah satu menu di sini',
        'babi is pork' => 'menu babi panggang',
        'kaya is "like"' => 'kaya gini sih',
    ]);
});

describe('parah and lumayan in the shipped lexicon', function () {
    it('reads parah as negative on its own and as a booster after a sentiment word', function () {
        expect(shippedCompound('Filmnya parah'))->toBeLessThan(0.0);
        expect(shippedCompound('keren parah'))->toBeGreaterThan(shippedCompound('keren'));
        expect(shippedCompound('keren parah'))->toBe(shippedCompound('keren banget'));
    });

    it('reads lumayan as mildly positive on its own and as a dampener before a sentiment word', function () {
        expect(shippedCompound('lumayan'))->toBeGreaterThan(0.0);
        expect(shippedCompound('lumayan'))->toBeLessThan(shippedCompound('bagus'));
        expect(shippedCompound('lumayan bagus'))->toBeLessThan(shippedCompound('bagus'));
        expect(shippedCompound('lumayan bagus'))->toBeGreaterThan(0.0);
    });

    it('lets lumayan dampen parah when they meet', function () {
        expect(shippedCompound('lumayan parah'))->toBeLessThan(0.0);
        expect(shippedCompound('lumayan parah'))->toBeGreaterThan(shippedCompound('parah'));
    });
});
