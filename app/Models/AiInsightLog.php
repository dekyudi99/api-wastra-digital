<?php
// app/Models/AiInsightLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInsightLog extends Model
{
    protected $fillable = [
        'mode','endpoint','payload_hash','payload','response',
        'manual_score','manual_note','prompt_version'
    ];

    protected $casts = [
        'payload'  => 'array',
        'response' => 'array',
    ];
}
