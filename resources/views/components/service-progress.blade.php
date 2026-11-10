@php
    $state = app(\App\Services\ServiceSchedule::class)->for($record);
    $colors = [
        \App\Services\ServiceSchedule::STATE_LATE => '#e5484d',
        \App\Services\ServiceSchedule::STATE_SOON => '#f59e0b',
        \App\Services\ServiceSchedule::STATE_OK => '#10b981',
        \App\Services\ServiceSchedule::STATE_NONE => '#94a3b8',
    ];
    $hasCycle = $state['state'] !== \App\Services\ServiceSchedule::STATE_NONE;
    $progress = max($state['date_progress'] ?? 0, $state['km_progress'] ?? 0);
    $width = $state['state'] === \App\Services\ServiceSchedule::STATE_LATE ? 100 : (int) round($progress * 100);
    $summary = app(\App\Services\ServiceSchedule::class)->summary($state);
@endphp

<div class="space-y-1.5">
    <p class="text-sm" style="color: {{ $colors[$state['state']] }}; font-weight: 600;">{{ $summary }}</p>

    @if ($hasCycle)
        <div style="height: 6px; border-radius: 3px; background: rgba(148, 163, 184, 0.25); overflow: hidden;" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $width }}">
            <div style="height: 100%; width: {{ $width }}%; border-radius: 3px; background: {{ $colors[$state['state']] }};"></div>
        </div>
    @endif
</div>
