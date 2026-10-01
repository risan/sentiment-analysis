<?php

declare(strict_types=1);

// Indonesian rule data, authored for this package (MIT). Valences are on VADER's scale:
// a booster adds 0.293 to the word it modifies, a dampener subtracts it.
//
// Rule words never carry a lexicon valence of their own, except the dual-role words listed
// in 'dualRole' (they are both sentiment words and modifiers). tools/build-lexicons.php
// checks this against tools/data/id/lexicon.tsv.

return [
    // Scale the valence of a sentiment word within three words after them, like VADER's "not".
    'negations' => [
        'tidak', 'tak', 'bukan', 'belum', 'jangan', 'tanpa', 'kurang',
        // informal spellings
        'ga', 'gak', 'nggak', 'enggak', 'engga', 'ngga', 'gk', 'tdk', 'ndak', 'nda', 'kagak', 'blm', 'jgn', 'bkn',
    ],

    // Modify a sentiment word that FOLLOWS them (up to three words after).
    'boosters' => [
        'sangat' => 0.293,
        'amat' => 0.293,
        'paling' => 0.293,
        'terlalu' => 0.293,
        'sungguh' => 0.293,
        'begitu' => 0.293,
        'sangatlah' => 0.293,
        'makin' => 0.293,
        'semakin' => 0.293,
        'lebih' => 0.293,
        'super' => 0.293,
        'benar-benar' => 0.293,
        'bener-bener' => 0.293,
        'bnr2' => 0.293,
        'sgt' => 0.293,
        'agak' => -0.293,
        'cukup' => -0.293,
        'sedikit' => -0.293,
        'lumayan' => -0.293,
        'rada' => -0.293,
        'setengah' => -0.293,
        'kurang lebih' => -0.293,
    ],

    // Modify a sentiment word that PRECEDES them (one or two words before): "bagus banget".
    'postBoosters' => [
        'banget' => 0.293,
        'bgt' => 0.293,
        'sekali' => 0.293,
        'bener' => 0.293,
        'amat' => 0.293,
        'abis' => 0.293,
        'parah' => 0.293,
    ],

    // Behave like "but". Not "padahal": its clause is concessive and the main point comes before it.
    'contrasts' => ['tapi', 'tetapi', 'tp', 'namun', 'sayangnya'],

    // Two-word modifiers merged into one token before any rule runs.
    'phrases' => ['kurang lebih'],

    // Both a sentiment word and a modifier; the engine decides by position.
    'dualRole' => ['parah', 'lumayan'],

    // Suffixes tried, in this order, when a word is not in the lexicon.
    'clitics' => ['nya', 'lah', 'kah', 'pun'],

    'reduplication' => true,

    'englishQuirks' => false,
];
