<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\AccountService;
use Illuminate\Console\Command;

class BackfillAccountPaths extends Command
{
    protected $signature = 'accounts:backfill-paths {--tenant= : معرف شركة محددة}';

    protected $description = 'تحديث path و is_group لكل حسابات الشركة';

    public function handle(AccountService $service): int
    {
        $tenantOpt = $this->option('tenant');
        $tenants = $tenantOpt
            ? Tenant::where('id', (int) $tenantOpt)->get()
            : Tenant::all();

        foreach ($tenants as $tenant) {
            $service->backfillPaths((int) $tenant->id);
            $this->info("✓ {$tenant->name} (id: {$tenant->id})");
        }

        return self::SUCCESS;
    }
}
