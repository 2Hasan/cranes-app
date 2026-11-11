<?php

namespace App\Services;

use App\Models\Site;
use App\Models\TireIntake;
use App\Models\TireOwner;
use App\Models\TireOwnerReturn;
use App\Models\TireReturn;
use App\Models\TireTransfer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tracks tires per site and per owner. Good returns go back to the site they left,
 * damaged tires leave the stock, and tires given back to their owner leave the site.
 */
class TireStock
{
    public const STATUS_RETURNED = 'returned';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PENDING = 'pending';

    public function addIntake(
        Site $site,
        TireOwner $owner,
        int $quantity,
        Carbon $receivedOn,
        ?string $note,
        User $by,
    ): TireIntake {
        return TireIntake::create([
            'site_id' => $site->getKey(),
            'tire_owner_id' => $owner->getKey(),
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
        TireOwner $owner,
        int $quantity,
        string $handledBy,
        Carbon $movedAt,
        ?string $note,
        User $by,
    ): TireTransfer {
        if ($from->getKey() === $to->getKey()) {
            throw ValidationException::withMessages(['to_site_id' => 'لا يمكن النقل لنفس الموقع.']);
        }

        return DB::transaction(function () use ($from, $to, $owner, $quantity, $handledBy, $movedAt, $note, $by): TireTransfer {
            $available = $this->balance($from, $owner);

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "الكمية ({$quantity}) أكبر من المتوفر في {$from->name} لدى {$owner->name} ({$available}).",
                ]);
            }

            return TireTransfer::create([
                'from_site_id' => $from->getKey(),
                'to_site_id' => $to->getKey(),
                'tire_owner_id' => $owner->getKey(),
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
     * Gives tires back to their owner from the site where they are held.
     *
     * @throws ValidationException
     */
    public function returnToOwner(
        Site $site,
        TireOwner $owner,
        int $quantity,
        Carbon $returnedAt,
        ?string $note,
        User $by,
    ): TireOwnerReturn {
        return DB::transaction(function () use ($site, $owner, $quantity, $returnedAt, $note, $by): TireOwnerReturn {
            $available = $this->balance($site, $owner);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'أدخل عدد أكبر من صفر.']);
            }

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "الكمية ({$quantity}) أكبر من رصيد {$owner->name} في {$site->name} ({$available}).",
                ]);
            }

            return TireOwnerReturn::create([
                'site_id' => $site->getKey(),
                'tire_owner_id' => $owner->getKey(),
                'quantity' => $quantity,
                'returned_at' => $returnedAt,
                'note' => $note,
                'created_by' => $by->getKey(),
            ]);
        });
    }

    /**
     * Tires of one owner currently held at the site.
     */
    public function balance(Site $site, TireOwner $owner): int
    {
        $siteId = $site->getKey();
        $ownerId = $owner->getKey();

        $intakes = (int) TireIntake::query()
            ->where('site_id', $siteId)
            ->where('tire_owner_id', $ownerId)
            ->sum('quantity');

        $sentOut = (int) TireTransfer::query()
            ->where('from_site_id', $siteId)
            ->where('tire_owner_id', $ownerId)
            ->sum('quantity');

        $receivedBack = (int) TireReturn::query()
            ->join('tire_transfers', 'tire_transfers.id', '=', 'tire_returns.tire_transfer_id')
            ->where('tire_transfers.from_site_id', $siteId)
            ->where('tire_transfers.tire_owner_id', $ownerId)
            ->sum('tire_returns.returned_quantity');

        $sentIn = (int) TireTransfer::query()
            ->where('to_site_id', $siteId)
            ->where('tire_owner_id', $ownerId)
            ->sum('quantity');

        $leftAsReturnedOrDamaged = (int) TireReturn::query()
            ->join('tire_transfers', 'tire_transfers.id', '=', 'tire_returns.tire_transfer_id')
            ->where('tire_transfers.to_site_id', $siteId)
            ->where('tire_transfers.tire_owner_id', $ownerId)
            ->selectRaw('COALESCE(SUM(tire_returns.returned_quantity + tire_returns.damaged_quantity), 0) as total')
            ->value('total');

        $givenToOwner = (int) TireOwnerReturn::query()
            ->where('site_id', $siteId)
            ->where('tire_owner_id', $ownerId)
            ->sum('quantity');

        return $intakes - $sentOut + $receivedBack + $sentIn - $leftAsReturnedOrDamaged - $givenToOwner;
    }

    /**
     * Every site and owner pair that still holds tires, for the balance overview.
     *
     * @return Collection<int, array{site: Site, owner: TireOwner, balance: int}>
     */
    public function balances(): Collection
    {
        $rows = collect();

        foreach (Site::query()->orderBy('name')->get() as $site) {
            foreach (TireOwner::query()->orderBy('name')->get() as $owner) {
                $balance = $this->balance($site, $owner);

                if ($balance > 0) {
                    $rows->push(['site' => $site, 'owner' => $owner, 'balance' => $balance]);
                }
            }
        }

        return $rows;
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
