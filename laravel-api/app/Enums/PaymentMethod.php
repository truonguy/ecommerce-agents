<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case COD = 'COD';
    case VNPAY = 'VNPAY';
}
