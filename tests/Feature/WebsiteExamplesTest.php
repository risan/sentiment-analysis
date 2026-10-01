<?php

declare(strict_types=1);

it('keeps the website examples in sync with the library', function () {
    $committed = dirname(__DIR__, 2) . '/website/src/data/examples.json';
    $regenerated = sys_get_temp_dir() . '/sentiment-examples-' . bin2hex(random_bytes(4)) . '.json';

    exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/tools/website-examples.php') . ' ' . escapeshellarg($regenerated) . ' 2>&1',
        $log,
        $exitCode,
    );

    expect($exitCode)->toBe(0, implode("\n", $log));
    expect(file_get_contents($regenerated))->toBe(file_get_contents($committed));

    unlink($regenerated);
});

it('covers both languages with scores for every sentence', function () {
    $examples = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2) . '/website/src/data/examples.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($examples)->toBeArray();

    $languages = array_count_values(array_column($examples, 'language'));

    expect($languages['en'])->toBeGreaterThanOrEqual(12);
    expect($languages['id'])->toBeGreaterThanOrEqual(8);

    foreach ($examples as $example) {
        expect(array_keys($example))->toBe(['text', 'language', 'label', 'compound', 'positive', 'negative', 'neutral']);
    }
});
