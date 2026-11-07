<?php

namespace App\Http\Controllers;

use App\Models\Crane;
use App\Services\CraneReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CraneReportController extends Controller
{
    public function __invoke(Request $request, int $crane, CraneReportPdf $reportPdf): Response
    {
        $user = $request->user();

        abort_unless($user->can('report.export'), 403);

        // Scoped lookup: a technician cannot download a report for another site's crane.
        $visibleCrane = Crane::query()->visibleTo($user)->findOrFail($crane);

        return response($reportPdf->render($visibleCrane), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="crane-'.$visibleCrane->code.'.pdf"',
        ]);
    }
}
