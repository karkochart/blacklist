<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionType: string
{
    case DAILY = 'daily';
    case MONTHLY = 'monthly';

    public function duration(): \DateInterval
    {
        return new \DateInterval(match ($this) {
            self::DAILY => 'P1D',
            self::MONTHLY => 'P1M',
        });
    }

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Щоденна',
            self::MONTHLY => 'Щомісячна',
        };
    }
}
