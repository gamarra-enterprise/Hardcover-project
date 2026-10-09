<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The Club's list for staff: who is on it, and a CSV to use with a mailing tool. */
class ClubController extends Controller
{
    public function index(): View
    {
        return view('admin.club.index', [
            'subscribers' => NewsletterSubscriber::latest('subscribed_at')->paginate(30),
            'active' => NewsletterSubscriber::whereNull('unsubscribed_at')->count(),
        ]);
    }

    public function export(): StreamedResponse
    {
        ActivityLog::record('club.exported', 'Exportó la lista del Club de lectores');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['correo', 'suscrito'], escape: '');
            NewsletterSubscriber::whereNull('unsubscribed_at')->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [$row->email, $row->subscribed_at->format('Y-m-d')], escape: '');
                }
            });
        }, 'club-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
