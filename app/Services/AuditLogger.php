<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLog;

class AuditLogger
{
    public static function log(
        User $actor,
        string $action,
        Model $model,
        array $old = null,
        array $new = null
    ) {
        AuditLog::create([
            'actor_id' => $actor->id,
            'actor_role' => $actor->role,
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'old_values' => $old ? json_encode($old) : null,
            'new_values' => $new ? json_encode($new) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}