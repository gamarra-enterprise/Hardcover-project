<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Landing page after login: staff go to their panel, customers to their account.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user->role === UserRole::SUPER_ADMIN) {
            return redirect()->route('super.index');
        }

        return $user->isStaff() ? redirect()->route('admin.index') : view('dashboard');
    }
}
