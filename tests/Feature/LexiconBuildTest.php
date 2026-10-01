<?php

declare(strict_types=1);

/**
 * @return list<string> relative paths of every file under $directory, sorted
 */
function listFiles(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile()) {
            $files[] = substr($file->getPathname(), strlen($directory) + 1);
        }
    }

    sort($files);

    return $files;
}

function removeDirectory(string $directory): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $entry) {
        if ($entry instanceof SplFileInfo) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
    }

    rmdir($directory);
}

it('regenerates the committed resources byte for byte', function () {
    $resources = dirname(__DIR__, 2) . '/resources';
    $output = sys_get_temp_dir() . '/sentiment-build-' . bin2hex(random_bytes(4));

    exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/tools/build-lexicons.php') . ' ' . escapeshellarg($output) . ' 2>&1',
        $log,
        $exitCode,
    );

    expect($exitCode)->toBe(0, implode("\n", $log));
    expect(listFiles($output))->toBe(listFiles($resources));

    foreach (listFiles($resources) as $file) {
        expect(file_get_contents($output . '/' . $file))->toBe(file_get_contents($resources . '/' . $file), $file);
    }

    removeDirectory($output);
});

describe('English lexicon', function () {
    beforeEach(function () {
        $this->lexicon = require dirname(__DIR__, 2) . '/resources/en/lexicon.php';
    });

    it('lets the last duplicate line win like VADER does', function () {
        expect($this->lexicon['lol'])->toBe(1.8);
    });

    it('keeps keys verbatim so upper-case emoticons do not overwrite lower-case ones', function () {
        expect($this->lexicon[':P'])->toBe(1.4);
        expect($this->lexicon[':p'])->toBe(1.0);
    });

    it('stores numeric words as integer keys that string lookups still find', function () {
        expect(isset($this->lexicon['1337']))->toBeTrue();
        expect($this->lexicon['143'])->toBe(3.2);
    });
});

describe('Indonesian lexicon', function () {
    beforeEach(function () {
        $this->lexicon = require dirname(__DIR__, 2) . '/resources/id/lexicon.php';
    });

    it('has at least 2,500 words, all scored within VADER\'s range and none neutral', function () {
        expect(count($this->lexicon))->toBeGreaterThanOrEqual(2500);

        foreach ($this->lexicon as $word => $valence) {
            expect($valence)->not->toBe(0.0, (string) $word);
            expect(abs($valence))->toBeLessThanOrEqual(4.0, (string) $word);
        }
    });

    it('keeps the emoji description words and emoticons that VADER scores, minus the collisions', function () {
        expect($this->lexicon)->toHaveKey('heart');
        expect($this->lexicon)->toHaveKey(':)');
        expect($this->lexicon)->not->toHaveKey('no');
    });
});

describe('English emoji map', function () {
    it('keeps only single code point emoji, the only ones the reference can match', function () {
        $emoji = require dirname(__DIR__, 2) . '/resources/en/emoji.php';

        expect($emoji)->not->toBeEmpty();
        expect($emoji['😊'])->toBe('smiling face with smiling eyes');

        foreach (array_keys($emoji) as $key) {
            expect(mb_strlen((string) $key, 'UTF-8'))->toBe(1);
        }
    });
});
