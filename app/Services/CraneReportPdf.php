<?php

namespace App\Services;

use App\Models\Crane;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class CraneReportPdf
{
    public function __construct(private ServiceSchedule $schedule) {}

    /**
     * Renders a one-crane report (details, service status, maintenance log and faults) as PDF bytes.
     */
    public function render(Crane $crane): string
    {
        $crane->load([
            'site',
            'maintenanceRecords' => fn ($query) => $query
                ->with(['creator'])
                ->orderByDesc('performed_on')
                ->orderByDesc('id'),
            'faults' => fn ($query) => $query
                ->with(['reporter', 'assignee', 'maintenanceRecord'])
                ->orderByDesc('reported_at'),
        ]);

        $html = View::make('reports.crane', [
            'crane' => $crane,
            'schedule' => $this->schedule->for($crane),
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->render();

        $tempDirectory = storage_path('app/mpdf');

        if (! is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0775, true);
        }

        // mPDF loads the Arabic font and lays out pages in memory; the 128M default is too small.
        ini_set('memory_limit', '512M');

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDirectory,
        ]);

        // A plain HTML footer avoids mPDF's default bordered footer table.
        $mpdf->SetHTMLFooter(
            '<div style="text-align: center; font-size: 8pt; color: #566774;">'
            .'تقرير الرافعة '.e($crane->code).'   —   صفحة {PAGENO} من {nbpg}'
            .'</div>'
        );
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
