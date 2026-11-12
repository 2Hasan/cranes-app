<?php

namespace App\Services;

use App\Enums\CraneStatus;
use App\Enums\FaultStatus;
use App\Models\Crane;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class AllCranesReportPdf
{
    public function __construct(
        private ServiceSchedule $schedule,
        private CraneAttention $attention,
    ) {}

    /**
     * Renders one PDF covering every crane the user can see: totals, cranes per site,
     * open faults, and each crane's next service with its due date and kilometres.
     */
    public function render(User $user): string
    {
        $cranes = Crane::query()->visibleTo($user)->with(['site', 'faults' => fn ($query) => $query
            ->whereIn('status', [FaultStatus::Open, FaultStatus::InProgress])
            ->orderByDesc('reported_at')])
            ->get()
            ->keyBy('id');

        $orderedCranes = collect($this->attention->sortedIds($user))
            ->map(fn (int $id): ?Crane => $cranes->get($id))
            ->filter()
            ->values();

        $html = View::make('reports.all-cranes', [
            'cranes' => $orderedCranes->map(fn (Crane $crane): array => [
                'crane' => $crane,
                'schedule' => $this->schedule->for($crane),
            ]),
            'statusCounts' => $cranes->groupBy(fn (Crane $crane): string => $crane->status->value)
                ->map(fn ($group): int => $group->count()),
            'siteCounts' => $cranes->groupBy(fn (Crane $crane): string => $crane->site?->name ?? '—')
                ->map(fn ($group): int => $group->count())
                ->sortKeys(),
            'faults' => $cranes->flatMap(fn (Crane $crane) => $crane->faults->map(fn ($fault) => [
                'crane' => $crane,
                'fault' => $fault,
            ]))->sortByDesc(fn (array $row) => $row['fault']->reported_at)->values(),
            'generatedAt' => now()->format('Y-m-d H:i'),
            'statusLabels' => collect(CraneStatus::cases())->mapWithKeys(fn (CraneStatus $status): array => [$status->value => $status->getLabel()]),
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
            .'تقرير جميع الرافعات   —   صفحة {PAGENO} من {nbpg}'
            .'</div>'
        );
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
