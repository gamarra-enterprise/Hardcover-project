<?php

namespace App\Http\Controllers\Super;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Accounts and roles. Super admin only; every role change is written to the activity record. */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('q'));
        $role = UserRole::tryFrom((string) $request->query('rol'));

        $users = User::query()
            ->withCount('orders')
            ->when($role, fn ($q) => $q->where('role', $role->value))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('super.users.index', ['users' => $users, 'search' => $search, 'role' => $role]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('changeRole', $user);

        $data = $request->validate(['role' => ['required', Rule::enum(UserRole::class)]]);
        $new = UserRole::from($data['role']);

        if ($request->user()->is($user)) {
            return back()->with('error', 'No puedes cambiar tu propio rol: pídeselo a otro super administrador.');
        }

        // Lock the super admins so two people cannot demote each other at the same time.
        $changed = DB::transaction(function () use ($user, $new) {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            if ($locked->role === $new) {
                return null;
            }

            $from = $locked->role;
            $locked->forceFill(['role' => $new])->save();

            return $from;
        });

        if ($changed === null) {
            return back()->with('notice', 'Sin cambios.');
        }

        ActivityLog::record('user.role_changed', "Rol de {$user->email}: {$changed->value} → {$new->value}", ['user_id' => $user->id, 'from' => $changed->value, 'to' => $new->value]);

        return back()->with('notice', "Rol de {$user->name} actualizado.");
    }

    /** For staff who lost their phone and their recovery codes. They set it up again on their next sign-in. */
    public function resetTwoFactor(Request $request, User $user, TwoFactor $twoFactor): RedirectResponse
    {
        Gate::authorize('changeRole', $user);

        if ($request->user()->is($user)) {
            return back()->with('error', 'Tu propia verificación se gestiona desde Seguridad.');
        }

        $twoFactor->disable($user);
        ActivityLog::record('security.2fa_reset', "Restableció la verificación en dos pasos de {$user->email}", ['user_id' => $user->id]);

        return back()->with('notice', "Verificación en dos pasos de {$user->name} restablecida.");
    }
}
