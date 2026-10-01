---
title: Installation
description: Install Sentiment Analysis for PHP with Composer. Requires PHP 8.3 or newer and the mbstring extension.
---

Install the package with Composer:

```bash
composer require risan/sentiment-analysis
```

Then check that it works. Save this as `check.php`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Risan\Sentiment\Sentiment;

echo Sentiment::analyze('Installation was great!')->label->value;
```

Run it with `php check.php`. It prints `positive`.

## Requirements

- PHP 8.3 or newer
- The `mbstring` extension

The package has no other dependencies. It does not call any outside service.

## Check the mbstring extension

If Composer complains about `ext-mbstring`, check that the extension is on:

```bash
php -m | grep mbstring
```

On Debian and Ubuntu, install it with `sudo apt install php-mbstring`. The official PHP Docker images already include it.

## Autoloading

Composer sets up autoloading for you. Frameworks load it by themselves. In a plain script, require the autoloader once:

```php
require __DIR__ . '/vendor/autoload.php';
```

## Speed tip

Turn on OPcache in production. It makes the word lists load much faster. See [Performance](/guides/performance/).

## Upgrading from v1

Version 2 is a rewrite with a new namespace and a new API. If you use the 1.x line, read [Upgrading from v1](/upgrading-from-v1/).
