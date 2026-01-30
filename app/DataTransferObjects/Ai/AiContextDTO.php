<?php

namespace App\DataTransferObjects\Ai;

class AiContextDTO
{
    public static function forBuyer(): array
    {
        return [
            'domain'      => 'produk wastra',
            'target_user' => 'pembeli umum',
            'language'    => 'id',
            'tone'        => 'informatif, netral',
            'goal'        => 'menjelaskan performa produk berdasarkan data'
        ];
    }

    public static function forSeller(): array
    {
        return [
            'domain'      => 'produk wastra',
            'target_user' => 'pengrajin',
            'language'    => 'id',
            'tone'        => 'analitis, praktis',
            'goal'        => 'memberi insight dan saran desain produk'
        ];
    }
}
