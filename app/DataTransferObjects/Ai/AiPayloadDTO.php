<?php

namespace App\DataTransferObjects\Ai;

class AiPayloadDTO
{
    public static function make(array $context, array $analytics): array
    {
        return [
            'context'   => $context,
            'analytics' => $analytics
        ];
    }
}
