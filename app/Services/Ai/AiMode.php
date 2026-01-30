<?php

namespace App\Services\Ai;

enum AiMode: string
{
    case BUYER  = 'buyer';
    case SELLER = 'seller';
}