<?php

namespace App\Console\Commands;

use Database\Seeders\DefaultChartOfAccountsSeeder;
use Database\Seeders\TenantAccountDefaultsSeeder;
use Database\Seeders\TenantDefaultDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedTenantDefaults extends Command
{
    protected $signature = 'tenants:seed-defaults
                            {--tenant= : معرف شركة محددة أو الكل}
                            {--accounts : دليل الحسابات + ربط الحسابات فقط}
                            {--force-chart : إعادة دليل الحسابات حتى لو موجوداً (updateOrCreate)}';

    protected $description = 'إضافة البيانات الافتراضية للشركات دون مسح البيانات التشغيلية';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? DB::table('tenants')->where('id', $tenantId)->get()
            : DB::table('tenants')->get();

        if ($tenants->isEmpty()) {
            $this->error('لا توجد شركات.');

            return self::FAILURE;
        }

        $tenantIds = $tenants->pluck('id')->map(fn ($id) => (int) $id)->all();
        $forceChart = (bool) $this->option('force-chart');

        $this->info("سيتم تشغيل البيانات الافتراضية على {$tenants->count()} شركة...");
        $this->newLine();

        if ($this->option('accounts')) {
            foreach ($tenants as $tenant) {
                $this->info("── {$tenant->name} (id: {$tenant->id}) ──");
                (new DefaultChartOfAccountsSeeder)->setCommand($this)->run((int) $tenant->id);
                (new TenantAccountDefaultsSeeder)->setCommand($this)->run((int) $tenant->id);
                $this->newLine();
            }
        } else {
            (new TenantDefaultDataSeeder)->setCommand($this)->run($tenantIds, $forceChart);
        }

        $this->info('══════════════════════════════════');
        $this->info('✅ تم الانتهاء!');
        $this->info('══════════════════════════════════');

        return self::SUCCESS;
    }
}
