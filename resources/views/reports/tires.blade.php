<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير المطاط</title>
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 10pt; color: #14202b; direction: rtl; }
        .header { border-bottom: 3px solid #f2b705; padding-bottom: 8px; margin-bottom: 10px; }
        h1 { font-size: 18pt; margin: 0; color: #16324a; }
        .muted { color: #566774; font-size: 9pt; }
        h2 { font-size: 12pt; color: #16324a; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #cdd5db; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #cdd5db; padding: 4px 6px; text-align: right; vertical-align: top; }
        th { background: #e3e8eb; font-weight: bold; }
        .returned { color: #1b7a49; font-weight: bold; }
        .partial { color: #a8500a; font-weight: bold; }
        .pending { color: #b3261e; font-weight: bold; }
        .empty { color: #566774; padding: 6px 0; }
        .small { font-size: 8.5pt; color: #566774; }
    </style>
</head>
<body>

    <div class="header">
        <h1>تقرير المطاط</h1>
        <div class="muted">تاريخ التقرير: <span dir="ltr">{{ $generatedAt }}</span></div>
    </div>

    <h2>الرصيد الحالي</h2>
    @if ($balances->isEmpty())
        <div class="empty">لا يوجد مطاط موجود حالياً.</div>
    @else
        <table>
            <tr>
                <th>الموقع</th>
                <th>التفاصيل</th>
                <th>المالك</th>
                <th>العدد الموجود</th>
            </tr>
            @foreach ($balances as $row)
                <tr>
                    <td>{{ $row['site']->name }}</td>
                    <td class="small">{{ $row['site']->details ?: '—' }}</td>
                    <td>{{ $row['owner']->name }}</td>
                    <td>{{ $row['balance'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>حركات النقل</h2>
    @if ($transfers->isEmpty())
        <div class="empty">لا توجد حركات نقل.</div>
    @else
        <table>
            <tr>
                <th>التاريخ والوقت</th>
                <th>المالك</th>
                <th>من</th>
                <th>إلى</th>
                <th>العدد</th>
                <th>من أخذها</th>
                <th>الاسترجاع</th>
                <th>الحالة</th>
            </tr>
            @foreach ($transfers as $row)
                @php($transfer = $row['transfer'])
                <tr>
                    <td dir="ltr">{{ $transfer->moved_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $transfer->owner->name }}</td>
                    <td>{{ $transfer->fromSite->name }}</td>
                    <td>{{ $transfer->toSite->name }}</td>
                    <td>{{ $transfer->quantity }}</td>
                    <td>{{ $transfer->handled_by }}</td>
                    <td class="small">
                        @forelse ($transfer->returns->sortBy('returned_at') as $return)
                            <div dir="ltr" style="text-align: right">{{ $return->returned_at->format('Y-m-d H:i') }}</div>
                            <div>{{ $return->returned_by }} · صالح {{ $return->returned_quantity }} · تالف {{ $return->damaged_quantity }}</div>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td class="{{ $row['status'] === 'returned' ? 'returned' : ($row['status'] === 'partial' ? 'partial' : 'pending') }}">
                        {{ $row['outstanding'] > 0 ? 'متبقي '.$row['outstanding'] : 'تم الاسترجاع' }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>التالف المسجل</h2>
    @if ($damages->isEmpty())
        <div class="empty">لا يوجد تالف مسجل.</div>
    @else
        <table>
            <tr>
                <th>التاريخ</th>
                <th>الموقع</th>
                <th>المالك</th>
                <th>العدد</th>
                <th>ملاحظة</th>
            </tr>
            @foreach ($damages as $damage)
                <tr>
                    <td dir="ltr">{{ $damage->recorded_on->format('Y-m-d') }}</td>
                    <td>{{ $damage->site->name }}</td>
                    <td>{{ $damage->owner->name }}</td>
                    <td>{{ $damage->quantity }}</td>
                    <td>{{ $damage->note ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>الرجوع للمالك</h2>
    @if ($ownerReturns->isEmpty())
        <div class="empty">لا توجد عمليات رجوع للمالك.</div>
    @else
        <table>
            <tr>
                <th>التاريخ والوقت</th>
                <th>من الموقع</th>
                <th>المالك</th>
                <th>العدد</th>
                <th>ملاحظة</th>
            </tr>
            @foreach ($ownerReturns as $return)
                <tr>
                    <td dir="ltr">{{ $return->returned_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $return->site->name }}</td>
                    <td>{{ $return->owner->name }}</td>
                    <td>{{ $return->quantity }}</td>
                    <td>{{ $return->note ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

</body>
</html>
