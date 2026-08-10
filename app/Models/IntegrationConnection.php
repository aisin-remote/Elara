<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class IntegrationConnection extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = ['workspace_id', 'provider', 'external_account_id', 'account_name', 'access_token', 'refresh_token', 'expires_at', 'scopes_json', 'settings_json', 'status', 'error_message', 'last_synced_at'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'provider' => IntegrationProvider::class,
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
        'scopes_json' => 'array',
        'settings_json' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(IntegrationLink::class, 'connection_id');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (Auth::check()) {
            $query->whereHas('workspace.memberships', fn (Builder $membership) => $membership
                ->where('user_id', Auth::id())
                ->where('status', 'active'));
        }

        return $query;
    }
}
