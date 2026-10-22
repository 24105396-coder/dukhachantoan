<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('view-audit');
        $filters = $this->filters($request, ['action', 'user', 'from', 'to']);
        $date = fn ($v) => $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;

        $logs = AuditLog::query()->with('user:id,name,email')
            ->when($filters['action'], fn ($q, $v) => $q->where('action', 'like', like_escape($v).'%'))
            ->when($filters['user'], fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('email', 'like', like_contains($v))))
            ->when($date($filters['from']), fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($date($filters['to']), fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->latest('id')->paginate(30)->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user ? ['name' => $log->user->name, 'email' => $log->user->email] : null,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'meta' => $log->meta,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs->toArray(),
            'filters' => $filters,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action')->all(),
        ]);
    }
}
