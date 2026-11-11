<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(SiteFactory::class)]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'details',
    ];

    /** @return HasMany<Crane, $this> */
    public function cranes(): HasMany
    {
        return $this->hasMany(Crane::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
