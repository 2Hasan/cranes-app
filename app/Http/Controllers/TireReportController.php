<?php

namespace App\Http\Controllers;

use App\Services\TireReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TireReportController extends Controller
{
    public function __invoke(Request $request, TireReportPdf $reportPdf): Response
    {
        abort_unless($request->user()->can('tires.manage'), 403);

        return response($reportPdf->render(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="tires-report.pdf"',
        ]);
    }
}
