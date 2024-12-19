<?php

namespace App\Enum;

enum PromotionStatus: string
{
    case NONE = 'none';
    case PROMOTION = 'promotion';
    case DISCOUNT = 'discount';
}

