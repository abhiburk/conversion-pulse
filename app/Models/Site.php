<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $domain
 * @property string $site_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'domain'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /**
     * Assign a public site key to new sites.
     */
    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            $site->site_key ??= 'pk_'.Str::lower(Str::random(32));
        });
    }

    /**
     * Determine whether a browser Origin header may send events for this site.
     *
     * The site's domain and any of its subdomains are allowed. Requests without an Origin header come from non-browser clients and are allowed, since the site key is public and the origin can be forged outside a browser anyway.
     */
    public function allowsOrigin(?string $origin): bool
    {
        if ($origin === null || $origin === '') {
            return true;
        }

        $host = parse_url($origin, PHP_URL_HOST);

        if (! is_string($host)) {
            return false;
        }

        $port = parse_url($origin, PHP_URL_PORT);
        $originHost = Str::lower($host).($port !== null ? ":{$port}" : '');

        return $originHost === $this->domain || Str::endsWith($originHost, ".{$this->domain}");
    }

    /**
     * Get the workspace that owns the site.
     *
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the campaigns running on the site.
     *
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
