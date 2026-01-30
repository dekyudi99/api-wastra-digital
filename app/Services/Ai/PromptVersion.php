<?php

namespace App\Services\Ai;

class PromptVersion
{
    public const CURRENT = 'v2';

    public static function get(): string
    {
        return self::CURRENT;
    }
}