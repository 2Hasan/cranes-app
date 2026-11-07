<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير الرافعة {{ $crane->code }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 10pt; color: #14202b; direction: rtl; }
        .header { border-bottom: 3px solid #f2b705; padding-bottom: 8px; margin-bottom: 10px; }
        h1 { font-size: 18pt; margin: 0; color: #16324a; }
        .muted { color: #566774; font-size: 9pt; }
        h2 { font-size: 12pt; color: #16324a; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #cdd5db; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #cdd5db; padding: 4px 6px; text-align: right; vertical-align: top; }
        th { background: #e3e8eb; font-weight: bold; }
        .label { background: #f4f6f8; color: #566774; width: 18%; }
        .value { width: 32%; }
        .late { color: #b3261e; font-weight: bold; }
        .soon { color: #a8500a; font-weight: bold; }
        .ok { color: #1b7a49; font-weight: bold; }
        .none { color: #566774; }
        .empty { color: #566774; padding: 6px 0; }
        .description { background: #fbfbfc; }
        .resolved { color: #1b7a49; font-weight: bold; }
        .open { color: #b3261e; font-weight: bold; }
        .notes { border: 1px solid #cdd5db; background: #fbfbfc; padding: 6px 8px; white-space: pre-line; }
    </style>
</head>
<body>

    @php
        $stateLabel = fn (string $state): string => match ($state) {
            'late' => 'متأخرة',
            'soon' => 'قريبة',
            'ok' => 'مجدولة',
            default => 'غير محددة',
        };
        $openFaults = $crane->faults->whereIn('status', [App\Enums\FaultStatus::Open, App\Enums\FaultStatus::InProgress])->count();
    @endphp

    <div class="header">
        <h1>تقرير الرافعة {{ $crane->code }}</h1>
        <div class="muted">{{ $crane->name ?: $crane->type }} — {{ $crane->site?->name }} — تاريخ التقرير: <span dir="ltr">{{ $generatedAt }}</span></div>
    </div>

    <h2>بيانات الرافعة</h2>
    <table>
        <tr>
            <th class="label">الرقم</th><td class="value">{{ $crane->code }}</td>
            <th class="label">الاسم أو الطراز</th><td class="value">{{ $crane->name ?: '—' }}</td>
        </tr>
        <tr>
            <th class="label">النوع</th><td class="value">{{ $crane->type }}</td>
            <th class="label">الموقع</th><td class="value">{{ $crane->site?->name ?? '—' }}</td>
        </tr>
        <tr>
            <th class="label">السعة</th><td class="value">{{ $crane->capacity_ton ? rtrim(rtrim((string) $crane->capacity_ton, '0'), '.').' طن' : '—' }}</td>
            <th class="label">الرقم التسلسلي</th><td class="value">{{ $crane->serial_number ?: '—' }}</td>
        </tr>
        <tr>
            <th class="label">سنة الصنع</th><td class="value">{{ $crane->manufacture_year ?: '—' }}</td>
            <th class="label">الحالة</th><td class="value">{{ $crane->status->getLabel() }}</td>
        </tr>
        <tr>
            <th class="label">العداد الحالي</th><td class="value">{{ number_format($crane->current_km) }} كم</td>
            <th class="label">دورة الصيانة</th>
            <td class="value">
                @php
                    $cycle = array_filter([
                        $crane->service_interval_days ? 'كل '.$crane->service_interval_days.' يوم' : null,
                        $crane->service_interval_km ? 'كل '.number_format($crane->service_interval_km).' كم' : null,
                    ]);
                @endphp
                {{ $cycle ? implode(' / ', $cycle) : '—' }}
            </td>
        </tr>
    </table>

    @if ($crane->notes)
        <h2>ملاحظات</h2>
        <div class="notes">{{ $crane->notes }}</div>
    @endif

    <h2>حالة الصيانة القادمة</h2>
    <table>
        <tr>
            <th class="label">حسب التاريخ</th>
            <td class="value {{ $schedule['date_state'] }}">{{ $stateLabel($schedule['date_state']) }}</td>
            <th class="label">الموعد</th>
            <td class="value">
                {{ $schedule['next_date'] ?? '—' }}
                @if ($schedule['days_left'] !== null)
                    ({{ $schedule['days_left'] < 0 ? 'متأخرة '.abs($schedule['days_left']).' يوم' : 'باقي '.$schedule['days_left'].' يوم' }})
                @endif
            </td>
        </tr>
        <tr>
            <th class="label">حسب الكيلومتر</th>
            <td class="value {{ $schedule['km_state'] }}">{{ $stateLabel($schedule['km_state']) }}</td>
            <th class="label">الموعد</th>
            <td class="value">
                {{ $schedule['next_km'] !== null ? number_format($schedule['next_km']).' كم' : '—' }}
                @if ($schedule['km_left'] !== null)
                    ({{ $schedule['km_left'] < 0 ? 'تجاوزت '.number_format(abs($schedule['km_left'])).' كم' : 'باقي '.number_format($schedule['km_left']).' كم' }})
                @endif
            </td>
        </tr>
        <tr>
            <th class="label">الأعطال المفتوحة</th>
            <td class="value {{ $openFaults > 0 ? 'open' : 'none' }}" colspan="3">{{ $openFaults }}</td>
        </tr>
    </table>

    <h2>سجل الصيانة</h2>
    @if ($crane->maintenanceRecords->isEmpty())
        <p class="empty">لا يوجد سجل صيانة لهذه الرافعة.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 16%">النوع</th>
                    <th style="width: 13%">التاريخ</th>
                    <th style="width: 16%">الفني</th>
                    <th style="width: 12%">العداد (كم)</th>
                    <th>ما الذي تم</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($crane->maintenanceRecords as $record)
                    <tr>
                        <td>{{ $record->type->getLabel() }}</td>
                        <td>{{ $record->performed_on->format('Y-m-d') }}</td>
                        <td>{{ $record->technician?->name ?? '—' }}</td>
                        <td>{{ $record->km !== null ? number_format($record->km) : '—' }}</td>
                        <td>{{ $record->description }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>الأعطال بالتفصيل</h2>
    @if ($crane->faults->isEmpty())
        <p class="empty">لا توجد أعطال مسجلة لهذه الرافعة.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 30%">العطل</th>
                    <th style="width: 12%">الخطورة</th>
                    <th style="width: 14%">الحالة</th>
                    <th style="width: 18%">تاريخ البلاغ</th>
                    <th>بلّغ / المسؤول</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($crane->faults as $fault)
                    <tr>
                        <td><strong>{{ $fault->title }}</strong></td>
                        <td>{{ $fault->severity->getLabel() }}</td>
                        <td class="{{ $fault->status === App\Enums\FaultStatus::Resolved ? 'resolved' : 'open' }}">{{ $fault->status->getLabel() }}</td>
                        <td>{{ $fault->reported_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $fault->reporter?->name ?? '—' }} / {{ $fault->assignee?->name ?? '—' }}</td>
                    </tr>
                    @if ($fault->description)
                        <tr>
                            <td colspan="5" class="description"><strong>التفاصيل:</strong> {{ $fault->description }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="5" class="description">
                            @if ($fault->resolved_at)
                                <strong>أُغلق بتاريخ:</strong> {{ $fault->resolved_at->format('Y-m-d H:i') }}
                                @if ($fault->maintenanceRecord)
                                    — <strong>الإصلاح:</strong> {{ $fault->maintenanceRecord->type->getLabel() }} بتاريخ {{ $fault->maintenanceRecord->performed_on->format('Y-m-d') }}: {{ $fault->maintenanceRecord->description }}
                                @endif
                            @else
                                <strong>لم يُغلق بعد.</strong>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>
