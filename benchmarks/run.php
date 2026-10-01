<?php

declare(strict_types=1);

/*
 * Throughput and memory benchmark.
 *
 * Usage: php benchmarks/run.php [--seconds=1]
 * With OPcache: php -d opcache.enable_cli=1 benchmarks/run.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Risan\Sentiment\Analyzer;
use Risan\Sentiment\Language;

const SAMPLES = [
    'en' => [
        'tweet' => 'Just got the new phone and the camera is AMAZING!!! Battery life is not great though :(',
        'review' => [
            'The hotel was absolutely wonderful and the staff were very friendly!',
            'Breakfast was not great, but the room was clean and quiet.',
            'I really did not like the noisy street outside our window.',
            'The location is perfect, and the beds were super comfortable :)',
            'Check-in took forever, which was a bit annoying and disappointing.',
            'We loved the pool, the view and the friendly bartender.',
            'Prices are kind of high, but honestly it was worth it.',
            'The wifi was terrible and kept dropping every single evening.',
            'Would I stay again? Definitely yes, this place is AWESOME!',
            'Overall a great stay with a few small problems, nothing serious.',
        ],
    ],
    'id' => [
        'tweet' => 'Filmnya bagus banget, tapi endingnya agak mengecewakan 😭 sayang sekali!',
        'review' => [
            'Hotelnya sangat bagus dan pelayanannya ramah sekali, kami senang.',
            'Sarapannya tidak enak, tapi kamarnya bersih dan nyaman sekali.',
            'Saya benar-benar tidak suka dengan jalanan yang bising di luar jendela.',
            'Lokasinya sempurna dan kasurnya super nyaman banget :)',
            'Proses check-in lambat sekali, agak menyebalkan dan mengecewakan.',
            'Kami suka kolam renangnya, pemandangannya dan bartender yang ramah.',
            'Harganya lumayan mahal, tapi menurut saya sepadan dengan kualitasnya.',
            'Wifinya parah dan putus terus setiap malam, sangat buruk.',
            'Mau menginap lagi? Pasti, tempat ini keren banget dan mantap!',
            'Secara keseluruhan menginap di sini bagus, hanya ada sedikit masalah kecil.',
        ],
    ],
];

function parseSeconds(): float
{
    global $argv;

    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--seconds=')) {
            return max(0.1, (float) substr($argument, strlen('--seconds=')));
        }
    }

    return 1.0;
}

function wordCount(string $text): int
{
    return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
}

/**
 * @return array{iterations: int, seconds: float, peakBytes: int}
 */
function measure(Analyzer $analyzer, string $text, float $seconds): array
{
    for ($warmUp = 0; $warmUp < 20; $warmUp++) {
        $analyzer->analyze($text);
    }

    $memoryBefore = memory_get_usage();
    memory_reset_peak_usage();

    $iterations = 0;
    $start = hrtime(true);
    $deadline = $start + (int) ($seconds * 1e9);

    do {
        for ($batch = 0; $batch < 50; $batch++) {
            $analyzer->analyze($text);
        }

        $iterations += 50;
        $now = hrtime(true);
    } while ($now < $deadline);

    return [
        'iterations' => $iterations,
        'seconds' => ($now - $start) / 1e9,
        'peakBytes' => memory_get_peak_usage() - $memoryBefore,
    ];
}

$seconds = parseSeconds();
$opcache = function_exists('opcache_get_status') && (ini_get('opcache.enable_cli') === '1');

printf("PHP %s, OPcache %s, %.1f s per case\n\n", PHP_VERSION, $opcache ? 'on' : 'off', $seconds);
printf("%-9s %-8s %6s %14s %12s %12s\n", 'language', 'text', 'words', 'analyses/s', 'us/analysis', 'peak memory');

foreach (SAMPLES as $code => $samples) {
    $loadMemoryBefore = memory_get_usage();
    $loadStart = hrtime(true);
    $analyzer = new Analyzer(Language::from($code));
    $loadMilliseconds = (hrtime(true) - $loadStart) / 1e6;
    $loadKilobytes = (memory_get_usage() - $loadMemoryBefore) / 1024;

    $review = implode(' ', $samples['review']);
    $texts = [
        'tweet' => $samples['tweet'],
        'review' => $review,
        'long' => implode(' ', array_fill(0, 20, $review)),
    ];

    foreach ($texts as $name => $text) {
        $result = measure($analyzer, $text, $seconds);

        printf(
            "%-9s %-8s %6d %14s %12.1f %9.1f KB\n",
            $code,
            $name,
            wordCount($text),
            number_format($result['iterations'] / $result['seconds'], 0),
            $result['seconds'] / $result['iterations'] * 1e6,
            $result['peakBytes'] / 1024,
        );
    }

    printf("          first use of '%s' (load rules): %.2f ms, %.0f KB\n\n", $code, $loadMilliseconds, $loadKilobytes);
}

printf("Whole process peak memory: %.2f MB\n", memory_get_peak_usage() / 1048576);
