<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $action = trim((string) $request->query('accion'));

        $logs = ActivityLog::query()->with('user:id,name,email')
            ->when($action !== '', fn ($q) => $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $action).'%'))
            ->latest('id')->paginate(30)->withQueryString();

        return view('super.activity', ['logs' => $logs, 'action' => $action]);
    }
}
