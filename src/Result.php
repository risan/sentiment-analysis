<?php

declare(strict_types=1);

namespace Risan\Sentiment;

use JsonSerializable;

final readonly class Result implements JsonSerializable
{
    public function __construct(
        public Label $label,
        public float $compound,
        public float $positive,
        public float $negative,
        public float $neutral,
    ) {}

    public function isPositive(): bool
    {
        return $this->label === Label::Positive;
    }

    public function isNegative(): bool
    {
        return $this->label === Label::Negative;
    }

    public function isNeutral(): bool
    {
        return $this->label === Label::Neutral;
    }

    /**
     * @return array{label: string, compound: float, positive: float, negative: float, neutral: float}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label->value,
            'compound' => $this->compound,
            'positive' => $this->positive,
            'negative' => $this->negative,
            'neutral' => $this->neutral,
        ];
    }

    /**
     * @return array{label: string, compound: float, positive: float, negative: float, neutral: float}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
