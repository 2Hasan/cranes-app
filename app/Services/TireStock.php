<?php

namespace App\Services;

use App\Models\Site;
use App\Models\TireIntake;
use App\Models\TireReturn;
use App\Models\TireTransfer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tracks tires per site. Good returns go back to the site they left,
 * damaged tires leave the stock and are not counted in any balance.
 */
class TireStock
{
    public const STATUS_RETURNED = 'returned';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PENDING = 'pending';

    public function addIntake(Site $site, int $quantity, Carbon $receivedOn, ?string $note, User $by): TireIntake
    {
        return TireIntake::create([
            'site_id' => $site->getKey(),
            'quantity' => $quantity,
            'received_on' => $receivedOn->toDateString(),
            'note' => $note,
            'created_by' => $by->getKey(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function transfer(
        Site $from,
        Site $to,
        int $quantity,
        string $handledBy,
        Carbon $movedAt,
        ?string $note,
        User $by,
    ): TireTransfer {
        if ($from->getKey() === $to->getKey()) {
            throw ValidationException::withMessages(['to_site_id' => 'لا يمكن النقل لنفس الموقع.']);
        }

        return DB::transaction(function () use ($from, $to, $quantity, $handledBy, $movedAt, $note, $by): TireTransfer {
            $available = $this->balance($from);

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "الكمية ({$quantity}) أكبر من المتوفر في {$from->name} ({$available}).",
                ]);
            }

            return TireTransfer::create([
                'from_site_id' => $from->getKey(),
                'to_site_id' => $to->getKey(),
                'quantity' => $quantity,
                'handled_by' => $handledBy,
                'moved_at' => $movedAt,
                'note' => $note,
                'created_by' => $by->getKey(),
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function recordReturn(
        TireTransfer $transfer,
        int $returnedQuantity,
        int $damagedQuantity,
        string $returnedBy,
        Carbon $returnedAt,
        User $by,
    ): TireReturn {
        return DB::transaction(function () use ($transfer, $returnedQuantity, $damagedQuantity, $returnedBy, $returnedAt, $by): TireReturn {
            $outstanding = $this->outstanding($transfer);
            $total = $returnedQuantity + $damagedQuantity;

            if ($total <= 0) {
                throw ValidationException::withMessages(['returned_quantity' => 'أدخل كمية الاسترجاع أو التالف.']);
            }

            if ($total > $outstanding) {
                throw ValidationException::withMessages([
                    'returned_quantity' => "المجموع ({$total}) أكبر من الباقي عند الموقع ({$outstanding}).",
                ]);
            }

            return TireReturn::create([
                'tire_transfer_id' => $transfer->getKey(),
                'returned_quantity' => $returnedQuantity,
                'damaged_quantity' => $damagedQuantity,
                'returned_by' => $returnedBy,
                'returned_at' => $returnedAt,
                'created_by' => $by->getKey(),
            ]);
        });
    }

    /**
     * Tires currently held at the site.
     */
    public function balance(Site $site): int
    {
        $siteId = $site->getKey();

        $intakes = (int) TireIntake::query()->where('site_id', $siteId)->sum('quantity');
        $sentOut = (int) TireTransfer::query()->where('from_site_id', $siteId)->sum('quantity');
        $receivedBack = (int) TireReturn::query()
            ->join('tire_transfers', 'tire_transfers.id', '=', 'tire_returns.tire_transfer_id')
            ->where('tire_transfers.from_site_id', $siteId)
            ->sum('tire_returns.returned_quantity');
        $sentIn = (int) TireTransfer::query()->where('to_site_id', $siteId)->sum('quantity');
        $leftAsReturnedOrDamaged = (int) TireReturn::query()
            ->join('tire_transfers', 'tire_transfers.id', '=', 'tire_returns.tire_transfer_id')
            ->where('tire_transfers.to_site_id', $siteId)
            ->selectRaw('COALESCE(SUM(tire_returns.returned_quantity + tire_returns.damaged_quantity), 0) as total')
            ->value('total');

        return $intakes - $sentOut + $receivedBack + $sentIn - $leftAsReturnedOrDamaged;
    }

    public function returnedTotal(TireTransfer $transfer): int
    {
        return (int) $transfer->returns()->selectRaw('COALESCE(SUM(returned_quantity + damaged_quantity), 0) as total')->value('total');
    }

    /**
     * Tires from this transfer that have not come back yet.
     */
    public function outstanding(TireTransfer $transfer): int
    {
        return max(0, $transfer->quantity - $this->returnedTotal($transfer));
    }

    public function status(TireTransfer $transfer): string
    {
        $returned = $this->returnedTotal($transfer);

        return match (true) {
            $returned >= $transfer->quantity => self::STATUS_RETURNED,
            $returned === 0 => self::STATUS_PENDING,
            default => self::STATUS_PARTIAL,
        };
    }
}
