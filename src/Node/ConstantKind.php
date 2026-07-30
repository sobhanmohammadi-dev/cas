<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Node;

enum ConstantKind: string
{
    case Pi = 'pi';
    case E = 'e';

    public function approximateValue(): float
    {
        return match ($this) {
            self::Pi => M_PI,
            self::E => M_E,
        };
    }
}
