<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    /**
     * Get the users that belong to the workspace.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the sites tracked by the workspace.
     *
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Get the campaigns in the workspace.
     *
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Get the tracking events received for the workspace.
     *
     * @return HasMany<IngestedEvent, $this>
     */
    public function ingestedEvents(): HasMany
    {
        return $this->hasMany(IngestedEvent::class);
    }

    /**
     * Get the monitoring alerts raised for the workspace.
     *
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
