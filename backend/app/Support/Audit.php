<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Audit
{
    public static function record(Request $request, string $action, ?Model $entity = null, array $details = []): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'details' => $details ?: null,
            'ip_address' => $request->ip(),
        ]);
    }
}
