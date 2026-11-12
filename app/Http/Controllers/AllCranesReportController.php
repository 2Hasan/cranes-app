<?php

namespace App\Http\Controllers;

use App\Services\AllCranesReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AllCranesReportController extends Controller
{
    public function __invoke(Request $request, AllCranesReportPdf $reportPdf): Response
    {
        $user = $request->user();

        abort_unless($user->can('report.export'), 403);

        return response($reportPdf->render($user), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="all-cranes.pdf"',
        ]);
    }
}
