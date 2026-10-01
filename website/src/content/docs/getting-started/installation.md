---
title: Installation
description: Install Sentiment Analysis for PHP with Composer. Requires PHP 8.3 or newer and the mbstring extension.
---

## Requirements

- PHP 8.3 or newer
- The `mbstring` extension

The package has no other runtime dependencies. It does not call any external service.

## Install with Composer

```bash
composer require risan/sentiment-analysis
```

Composer sets up autoloading for you. In a plain script, require the autoloader once:

```php
require __DIR__ . '/vendor/autoload.php';
```

## Check that it works

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Risan\Sentiment\Sentiment;

echo Sentiment::analyze('Installation was great!')->label->value;
```

Run it with `php check.php`. It should print `positive`.

## Verify the extension

If Composer complains about `ext-mbstring`, check that it is enabled:

```bash
php -m | grep mbstring
```

On Debian and Ubuntu, install it with `sudo apt install php-mbstring`. The official Docker images of PHP include it already.

## Upgrading from v1

Version 2 is a rewrite with a new namespace and a new API. If you are coming from the 1.x line, read [Upgrading from v1](/upgrading-from-v1/).
