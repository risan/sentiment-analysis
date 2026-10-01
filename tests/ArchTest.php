<?php

declare(strict_types=1);

arch()->preset()->php();

arch('every source file declares strict types')
    ->expect('Risan\Sentiment')
    ->toUseStrictTypes();

arch('public classes are final, except enums')
    ->expect('Risan\Sentiment')
    ->classes()
    ->toBeFinal();

arch('internal classes are used only inside the package')
    ->expect('Risan\Sentiment\Internal')
    ->toOnlyBeUsedIn('Risan\Sentiment');
