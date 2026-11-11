<?php

namespace App\Services;

use App\Models\TireDamage;
use App\Models\TireOwnerReturn;
use App\Models\TireReturn;
use App\Models\TireTransfer;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class TireReportPdf
{
    public function __construct(private TireStock $stock) {}

    /**
     * Renders the tire report as PDF bytes. Each section lists its own records,
     * so transfers, returns, damage and owner returns read independently.
     */
    public function render(): string
    {
        $transfers = TireTransfer::query()
            ->with(['fromSite', 'toSite', 'owner'])
            ->orderByDesc('moved_at')
            ->get()
            ->map(fn (TireTransfer $transfer): array => [
                'transfer' => $transfer,
                'outstanding' => $this->stock->outstanding($transfer),
            ]);

        $returns = TireReturn::query()
            ->with(['transfer.fromSite', 'transfer.toSite', 'transfer.owner'])
            ->orderByDesc('returned_at')
            ->get();

        $html = View::make('reports.tires', [
            'balances' => $this->stock->balances(),
            'transfers' => $transfers,
            'returns' => $returns,
            'damages' => TireDamage::query()->with(['site', 'owner'])->orderByDesc('recorded_on')->get(),
            'ownerReturns' => TireOwnerReturn::query()->with(['site', 'owner'])->orderByDesc('returned_at')->get(),
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->render();

        $tempDirectory = storage_path('app/mpdf');

        if (! is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0775, true);
        }

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

        $mpdf->SetHTMLFooter(
            '<div style="text-align: center; font-size: 8pt; color: #566774;">'
            .'تقرير المطاط   —   صفحة {PAGENO} من {nbpg}'
            .'</div>'
        );
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
