<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';
    protected $fillable = [
        'actor_id',
        'actor_role',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    public function User() {
        return $this->belongsTo(User::class, 'actor_id', 'id');
    }
}
