<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage-users');
        $filters = $this->filters($request, ['q', 'role']);

        $users = User::query()->with('role')
            ->when($filters['role'], fn ($q, $v) => $q->whereHas('role', fn ($r) => $r->where('code', $v)))
            ->when($filters['q'], function ($q, $v) {
                $like = like_contains($v);
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('id')->paginate(20)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role?->code,
                'role_id' => $u->role_id,
                'organization' => $u->organization,
                'is_active' => (bool) $u->is_active,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'created_at' => $u->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users->toArray(),
            'roles' => Role::query()->orderBy('id')->get()
                ->map(fn ($r) => ['id' => $r->id, 'code' => $r->code, 'name' => __('enums.roles.'.$r->code)])->all(),
            'filters' => $filters,
        ]);
    }

    public function update(UserUpdateRequest $request, User $user, AuditLogger $audit, SessionRevoker $revoker): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $newRole = Role::findOrFail($data['role_id']);

        // Không cho admin tự hạ quyền hoặc tự khóa tài khoản mình
        if ($actor->is($user) && ($newRole->code !== Role::ADMIN || ! $data['is_active'])) {
            return back()->withErrors(['role_id' => __('messages.admin.cannot_demote_self')])->with('error', __('messages.admin.cannot_demote_self'));
        }

        $before = ['role_id' => $user->role_id, 'is_active' => $user->is_active];
        $user->forceFill(['role_id' => $newRole->id, 'is_active' => (bool) $data['is_active']])->save();
        if ($before['is_active'] && ! $user->is_active) {
            $revoker->revokeAll($user); // khóa tài khoản → mọi phiên đang mở bị hủy ngay
        }
        $audit->log('admin.user.update', $user, ['before' => $before, 'after' => $data]);

        return back()->with('success', __('messages.admin.user_updated'));
    }
}
