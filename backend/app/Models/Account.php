<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'code',
        'name',
        'name_en',
        'type',
        'normal_balance',
        'description',
        'is_system',
        'is_active',
        'level',
        'path',
        'currency',
        'opening_balance',
        'sort_order',
        'allow_manual_entry',
        'is_postable',
        'is_group',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'is_postable' => 'boolean',
        'is_group' => 'boolean',
        'allow_manual_entry' => 'boolean',
        'opening_balance' => 'decimal:4',
    ];

    protected $appends = ['allow_transactions'];

    public function getAllowTransactionsAttribute(): bool
    {
        return (bool) $this->is_postable;
    }

    public function isPostable(): bool
    {
        return (bool) $this->is_postable;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id')->orderBy('sort_order')->orderBy('code');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'account_branch');
    }

    public function costCenters(): BelongsToMany
    {
        return $this->belongsToMany(CostCenter::class, 'account_cost_center');
    }

    public function allowedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_user');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeGroups($query)
    {
        return $query->where('is_group', true);
    }

    public function scopeLeaves($query)
    {
        return $query->where('is_group', false)->where('is_postable', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function getFullPathAttribute(): string
    {
        return $this->path ?? $this->code;
    }

    public function getDepthAttribute(): int
    {
        return max(0, $this->level - 1);
    }

    public function isAncestorOf(Account $account): bool
    {
        $myPath = $this->path ?? $this->code;
        $otherPath = $account->path ?? $account->code;

        return $otherPath !== $myPath && str_starts_with($otherPath, $myPath.'/');
    }

    public function isDescendantOf(Account $account): bool
    {
        return $account->isAncestorOf($this);
    }

    public function canMoveTo(Account $newParent): bool
    {
        if ($this->id === $newParent->id) {
            return false;
        }
        if ($this->isAncestorOf($newParent)) {
            return false;
        }
        return $newParent->tenant_id === $this->tenant_id;
    }

    public function getTypeEnum(): AccountType
    {
        return AccountType::from($this->type);
    }

    public function getEffectiveNormalBalance(): string
    {
        if ($this->normal_balance && in_array($this->normal_balance, ['debit', 'credit'], true)) {
            return $this->normal_balance;
        }

        return $this->getTypeEnum()->normalBalance();
    }
}
