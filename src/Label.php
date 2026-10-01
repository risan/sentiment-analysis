<?php

declare(strict_types=1);

namespace Risan\Sentiment;

enum Label: string
{
    case Positive = 'positive';
    case Negative = 'negative';
    case Neutral = 'neutral';
}
