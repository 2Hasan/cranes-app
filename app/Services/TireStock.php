<?php

namespace App\Services;

use App\Models\Site;
use App\Models\TireDamage;
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

    /**
     * Finds the owner by name, ignoring extra spaces, or creates it.
     *
     * @throws ValidationException
     */
    public function ownerByName(string $name): TireOwner
    {
        return TireOwner::query()->firstOrCreate(['name' => $this->cleanName($name, 'owner_name', 'اسم المالك')]);
    }

    /**
     * Finds the site by name, ignoring extra spaces, or creates it. Details are kept on first creation.
     *
     * @throws ValidationException
     */
    public function siteByName(string $name, ?string $details = null): Site
    {
        $site = Site::query()->firstOrCreate(
            ['name' => $this->cleanName($name, 'site_name', 'اسم الموقع')],
            ['details' => $details !== null && trim($details) !== '' ? trim($details) : null],
        );

        return $site;
    }

    /**
     * @throws ValidationException
     */
    private function cleanName(string $name, string $field, string $label): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($clean === '') {
            throw ValidationException::withMessages([$field => "{$label} مطلوب."]);
        }

        return $clean;
    }

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
     * Records tires that were damaged while held at the site. They leave the stock.
     *
     * @throws ValidationException
     */
    public function recordDamage(
        Site $site,
        TireOwner $owner,
        int $quantity,
        Carbon $recordedOn,
        ?string $note,
        User $by,
    ): TireDamage {
        return DB::transaction(function () use ($site, $owner, $quantity, $recordedOn, $note, $by): TireDamage {
            $available = $this->balance($site, $owner);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'أدخل عدد أكبر من صفر.']);
            }

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "التالف ({$quantity}) أكبر من المتوفر في {$site->name} لدى {$owner->name} ({$available}).",
                ]);
            }

            return TireDamage::create([
                'site_id' => $site->getKey(),
                'tire_owner_id' => $owner->getKey(),
                'quantity' => $quantity,
                'recorded_on' => $recordedOn->toDateString(),
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

        $damaged = (int) TireDamage::query()
            ->where('site_id', $siteId)
            ->where('tire_owner_id', $ownerId)
            ->sum('quantity');

        return $intakes - $sentOut + $receivedBack + $sentIn - $leftAsReturnedOrDamaged - $givenToOwner - $damaged;
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

    /**
     * @throws ValidationException
     */
    public function updateIntake(TireIntake $intake, Site $site, TireOwner $owner, int $quantity, Carbon $receivedOn, ?string $note): TireIntake
    {
        return $this->guard(function () use ($intake, $site, $owner, $quantity, $receivedOn, $note): TireIntake {
            $this->positiveQuantity($quantity);

            $intake->update([
                'site_id' => $site->getKey(),
                'tire_owner_id' => $owner->getKey(),
                'quantity' => $quantity,
                'received_on' => $receivedOn->toDateString(),
                'note' => $note,
            ]);

            return $intake;
        });
    }

    /**
     * @throws ValidationException
     */
    public function deleteIntake(TireIntake $intake): void
    {
        $this->guard(fn () => $intake->delete());
    }

    /**
     * @throws ValidationException
     */
    public function updateTransfer(
        TireTransfer $transfer,
        Site $from,
        Site $to,
        TireOwner $owner,
        int $quantity,
        string $handledBy,
        Carbon $movedAt,
        ?string $note,
    ): TireTransfer {
        if ($from->getKey() === $to->getKey()) {
            throw ValidationException::withMessages(['to_site_name' => 'لا يمكن النقل لنفس الموقع.']);
        }

        return $this->guard(function () use ($transfer, $from, $to, $owner, $quantity, $handledBy, $movedAt, $note): TireTransfer {
            $this->positiveQuantity($quantity);

            $returned = $this->returnedTotal($transfer);

            if ($quantity < $returned) {
                throw ValidationException::withMessages([
                    'quantity' => "لا يمكن جعل العدد أقل من المسترجع ({$returned}).",
                ]);
            }

            $transfer->update([
                'from_site_id' => $from->getKey(),
                'to_site_id' => $to->getKey(),
                'tire_owner_id' => $owner->getKey(),
                'quantity' => $quantity,
                'handled_by' => $handledBy,
                'moved_at' => $movedAt,
                'note' => $note,
            ]);

            return $transfer;
        });
    }

    /**
     * Deleting a transfer also deletes the returns recorded against it.
     *
     * @throws ValidationException
     */
    public function deleteTransfer(TireTransfer $transfer): void
    {
        $this->guard(function () use ($transfer): void {
            $transfer->returns()->delete();
            $transfer->delete();
        });
    }

    /**
     * @throws ValidationException
     */
    public function updateReturn(TireReturn $return, int $returnedQuantity, int $damagedQuantity, string $returnedBy, Carbon $returnedAt): TireReturn
    {
        return $this->guard(function () use ($return, $returnedQuantity, $damagedQuantity, $returnedBy, $returnedAt): TireReturn {
            $total = $returnedQuantity + $damagedQuantity;

            if ($total <= 0) {
                throw ValidationException::withMessages(['returned_quantity' => 'أدخل كمية الاسترجاع أو التالف.']);
            }

            $transfer = $return->transfer;
            $othersTotal = $this->returnedTotal($transfer) - ($return->returned_quantity + $return->damaged_quantity);

            if ($othersTotal + $total > $transfer->quantity) {
                throw ValidationException::withMessages([
                    'returned_quantity' => 'المجموع أكبر من عدد النقلة الأصلية.',
                ]);
            }

            $return->update([
                'returned_quantity' => $returnedQuantity,
                'damaged_quantity' => $damagedQuantity,
                'returned_by' => $returnedBy,
                'returned_at' => $returnedAt,
            ]);

            return $return;
        });
    }

    /**
     * @throws ValidationException
     */
    public function deleteReturn(TireReturn $return): void
    {
        $this->guard(fn () => $return->delete());
    }

    /**
     * @throws ValidationException
     */
    public function updateDamage(TireDamage $damage, Site $site, TireOwner $owner, int $quantity, Carbon $recordedOn, ?string $note): TireDamage
    {
        return $this->guard(function () use ($damage, $site, $owner, $quantity, $recordedOn, $note): TireDamage {
            $this->positiveQuantity($quantity);

            $damage->update([
                'site_id' => $site->getKey(),
                'tire_owner_id' => $owner->getKey(),
                'quantity' => $quantity,
                'recorded_on' => $recordedOn->toDateString(),
                'note' => $note,
            ]);

            return $damage;
        });
    }

    /**
     * @throws ValidationException
     */
    public function deleteDamage(TireDamage $damage): void
    {
        $this->guard(fn () => $damage->delete());
    }

    /**
     * @throws ValidationException
     */
    public function updateOwnerReturn(TireOwnerReturn $return, Site $site, TireOwner $owner, int $quantity, Carbon $returnedAt, ?string $note): TireOwnerReturn
    {
        return $this->guard(function () use ($return, $site, $owner, $quantity, $returnedAt, $note): TireOwnerReturn {
            $this->positiveQuantity($quantity);

            $return->update([
                'site_id' => $site->getKey(),
                'tire_owner_id' => $owner->getKey(),
                'quantity' => $quantity,
                'returned_at' => $returnedAt,
                'note' => $note,
            ]);

            return $return;
        });
    }

    /**
     * @throws ValidationException
     */
    public function deleteOwnerReturn(TireOwnerReturn $return): void
    {
        $this->guard(fn () => $return->delete());
    }

    /**
     * Runs a change and rolls it back if any site would be left holding a negative count.
     *
     * @template T
     *
     * @param  callable(): T  $change
     * @return T
     *
     * @throws ValidationException
     */
    private function guard(callable $change): mixed
    {
        return DB::transaction(function () use ($change): mixed {
            $result = $change();

            $this->ensureNoNegativeBalances();

            return $result;
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureNoNegativeBalances(): void
    {
        foreach (Site::query()->get() as $site) {
            foreach (TireOwner::query()->get() as $owner) {
                $balance = $this->balance($site, $owner);

                if ($balance < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => "هذا التعديل يجعل رصيد {$owner->name} في {$site->name} سالباً ({$balance}).",
                    ]);
                }
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function positiveQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'أدخل عدد أكبر من صفر.']);
        }
    }
}
