<?php

declare(strict_types=1);

/*
 * Scores a fixed list of sentences with the real library and writes the result
 * to website/src/data/examples.json, which the website imports. The file is
 * committed because the website build has no PHP; a test fails when it is stale.
 *
 * Usage: php tools/website-examples.php [output-file]
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Language;

const EXAMPLES = [
    'en' => [
        'This package is awesome!',
        'The movie was good.',
        'The movie was not good.',
        "It isn't bad at all.",
        'The service was extremely good.',
        'The service was kind of good.',
        'The plot was good, but the ending was terrible.',
        'I LOVE this phone.',
        'The concert was great!!!',
        'Thanks for the help :)',
        'Loved the show 😍',
        'Worst day ever 😭',
        'The meeting is at 3 pm.',
    ],
    'id' => [
        'Filmnya bagus banget!',
        'Makanannya enak sekali.',
        'Pelayanannya tidak ramah.',
        'Tempatnya nyaman tapi harganya mahal.',
        'Aku BENCI antrean panjang!!!',
        'Hotelnya lumayan, tapi kamarnya kotor.',
        'Kamera hp ini keren parah 😍',
        'Besok rapat jam 3 sore.',
    ],
];

$output = $argv[1] ?? dirname(__DIR__) . '/website/src/data/examples.json';
$examples = [];

foreach (EXAMPLES as $code => $sentences) {
    $analyzer = new Analyzer(Language::from($code));

    foreach ($sentences as $text) {
        $examples[] = ['text' => $text, 'language' => $code] + $analyzer->analyze($text)->toArray();
    }
}

$json = json_encode($examples, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

if (!is_dir(dirname($output)) && !mkdir(dirname($output), 0o775, true)) {
    throw new RuntimeException('Cannot create ' . dirname($output));
}

file_put_contents($output, $json . "\n");
