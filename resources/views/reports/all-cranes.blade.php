<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير جميع الرافعات</title>
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 9.5pt; color: #14202b; direction: rtl; }
        .header { border-bottom: 3px solid #f2b705; padding-bottom: 8px; margin-bottom: 10px; }
        h1 { font-size: 18pt; margin: 0; color: #16324a; }
        .muted { color: #566774; font-size: 9pt; }
        h2 { font-size: 12pt; color: #16324a; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #cdd5db; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #cdd5db; padding: 4px 5px; text-align: right; vertical-align: top; }
        th { background: #e3e8eb; font-weight: bold; }
        .summary td { text-align: center; font-size: 11pt; font-weight: bold; }
        .late { color: #b3261e; font-weight: bold; }
        .soon { color: #a8500a; font-weight: bold; }
        .ok { color: #1b7a49; font-weight: bold; }
        .none { color: #566774; }
        .open { color: #b3261e; font-weight: bold; }
        .empty { color: #566774; padding: 6px 0; }
        .small { font-size: 8.5pt; color: #566774; }
    </style>
</head>
<body>

    @php
        $serviceLabel = fn (string $state): string => match ($state) {
            'late' => 'متأخرة',
            'soon' => 'قريبة',
            'ok' => 'مجدولة',
            default => 'غير محددة',
        };
        $serviceClass = fn (string $state): string => match ($state) {
            'late' => 'late',
            'soon' => 'soon',
            'ok' => 'ok',
            default => 'none',
        };
        $total = $cranes->count();
        $working = $statusCounts[App\Enums\CraneStatus::Working->value] ?? 0;
        $underMaintenance = $statusCounts[App\Enums\CraneStatus::UnderMaintenance->value] ?? 0;
        $stopped = $statusCounts[App\Enums\CraneStatus::Stopped->value] ?? 0;
    @endphp

    <div class="header">
        <h1>تقرير جميع الرافعات</h1>
        <div class="muted">تاريخ التقرير: <span dir="ltr">{{ $generatedAt }}</span></div>
    </div>

    <h2>الملخص</h2>
    <table class="summary">
        <tr>
            <td>إجمالي الرافعات<br>{{ $total }}</td>
            <td>متوفرة (تعمل)<br>{{ $working }}</td>
            <td>تحت الصيانة<br>{{ $underMaintenance }}</td>
            <td>متوقفة<br>{{ $stopped }}</td>
            <td>فيها أعطال مفتوحة<br>{{ $faults->pluck('crane.id')->unique()->count() }}</td>
        </tr>
    </table>

    <h2>عدد الرافعات في كل موقع</h2>
    @if ($siteCounts->isEmpty())
        <div class="empty">لا توجد رافعات.</div>
    @else
        <table>
            <tr>
                <th>الموقع</th>
                <th>عدد الرافعات</th>
            </tr>
            @foreach ($siteCounts as $siteName => $count)
                <tr>
                    <td>{{ $siteName }}</td>
                    <td>{{ $count }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>الأعطال غير المصلحة</h2>
    @if ($faults->isEmpty())
        <div class="empty">لا توجد أعطال مفتوحة.</div>
    @else
        <table>
            <tr>
                <th>الرافعة</th>
                <th>الموقع</th>
                <th>العطل</th>
                <th>الخطورة</th>
                <th>الحالة</th>
                <th>تاريخ البلاغ</th>
            </tr>
            @foreach ($faults as $row)
                <tr>
                    <td>{{ $row['crane']->code }}</td>
                    <td>{{ $row['crane']->site?->name ?? '—' }}</td>
                    <td>{{ $row['fault']->title }}</td>
                    <td>{{ $row['fault']->severity->getLabel() }}</td>
                    <td class="open">{{ $row['fault']->status->getLabel() }}</td>
                    <td dir="ltr">{{ $row['fault']->reported_at->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>الصيانة القادمة لكل رافعة</h2>
    @if ($cranes->isEmpty())
        <div class="empty">لا توجد رافعات.</div>
    @else
        <table>
            <tr>
                <th>الرافعة</th>
                <th>الموقع</th>
                <th>الحالة</th>
                <th>العداد الحالي (كم)</th>
                <th>موعد الصيانة القادم</th>
                <th>الصيانة القادمة بالكيلومتر</th>
                <th>الوضع</th>
            </tr>
            @foreach ($cranes as $row)
                @php($crane = $row['crane'])
                @php($schedule = $row['schedule'])
                <tr>
                    <td>{{ $crane->code }}<br><span class="small">{{ $crane->name ?: $crane->type }}</span></td>
                    <td>{{ $crane->site?->name ?? '—' }}</td>
                    <td>{{ $statusLabels[$crane->status->value] ?? '—' }}</td>
                    <td dir="ltr">{{ number_format($crane->current_km) }}</td>
                    <td dir="ltr">
                        {{ $schedule['next_date'] ?? '—' }}
                        @if ($schedule['days_left'] !== null)
                            <br><span class="small">{{ $schedule['days_left'] < 0 ? 'متأخرة '.abs($schedule['days_left']).' يوم' : 'بعد '.$schedule['days_left'].' يوم' }}</span>
                        @endif
                    </td>
                    <td dir="ltr">
                        {{ $schedule['next_km'] !== null ? number_format($schedule['next_km']) : '—' }}
                        @if ($schedule['km_left'] !== null)
                            <br><span class="small">{{ $schedule['km_left'] < 0 ? 'تجاوزت '.number_format(-$schedule['km_left']) : 'بعد '.number_format($schedule['km_left']) }} كم</span>
                        @endif
                    </td>
                    <td class="{{ $serviceClass($schedule['state']) }}">{{ $serviceLabel($schedule['state']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

</body>
</html>
