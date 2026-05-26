<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Tenant;
use App\Services\AccountService;
use Database\Seeders\DefaultChartOfAccountsSeeder;
use Database\Seeders\TenantAccountDefaultsSeeder;
use Illuminate\Console\Command;

class SeedChartOfAccounts extends Command
{
    protected $signature = 'accounts:seed-chart
                            {--tenant= : معرف الشركة}
                            {--slug= : معرف الشركة المختصر مثل first-company أو firstclick-erp}
                            {--force : إضافة الحسابات الناقصة فقط (لا يحذف الموجود)}';

    protected $description = 'زرع دليل الحسابات الاحترافي لشركة محددة';

    public function handle(AccountService $accountService): int
    {
        $tenant = $this->resolveTenant();
        if (! $tenant) {
            $this->error('لم تُعثر على الشركة. استخدم --tenant=1 أو --slug=first-company');

            return self::FAILURE;
        }

        $countBefore = Account::where('tenant_id', $tenant->id)->count();
        $this->info("الشركة: {$tenant->name} (id: {$tenant->id}, slug: {$tenant->slug})");
        $this->info("حسابات حالية: {$countBefore}");

        if ($countBefore === 0) {
            (new DefaultChartOfAccountsSeeder)->setCommand($this)->run($tenant->id);
        } elseif ($this->option('force')) {
            $this->warn('يوجد دليل مسبق — يُضاف الناقص فقط عبر updateOrCreate داخل السيدر.');
            (new DefaultChartOfAccountsSeeder)->setCommand($this)->run($tenant->id, true);
        } else {
            $this->warn('الدليل موجود. للإضافة الناقصة: --force');
        }

        $accountService->backfillPaths($tenant->id);
        (new TenantAccountDefaultsSeeder)->setCommand($this)->run($tenant->id);

        $countAfter = Account::where('tenant_id', $tenant->id)->count();
        $this->newLine();
        $this->info("✅ انتهى — إجمالي الحسابات: {$countAfter}");

        if ($countAfter === 0) {
            $this->error('لم يُنشأ أي حساب. راجع سجل الأخطاء أعلاه.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        if ($this->option('tenant')) {
            return Tenant::find((int) $this->option('tenant'));
        }
        if ($this->option('slug')) {
            return Tenant::where('slug', $this->option('slug'))->first();
        }

        return null;
    }
}
