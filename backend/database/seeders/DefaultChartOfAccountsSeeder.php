<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Tenant;
use App\Services\AccountService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * دليل حسابات احترافي متعدد المستويات مع path وترميز هرمي.
 * لا يُستبدل دليل موجود — للشركات الفارغة فقط.
 */
class DefaultChartOfAccountsSeeder extends Seeder
{
    public function run(?int $tenantId = null, bool $force = false): void
    {
        $tenantId = $tenantId ?? 1;
        if (! Tenant::find($tenantId)) {
            $this->command?->warn("Tenant {$tenantId} not found. Skipping chart of accounts seed.");

            return;
        }

        $existingCount = Account::where('tenant_id', $tenantId)->count();
        if (! $force && $existingCount > 0) {
            $this->command?->line("  ○ Tenant {$tenantId}: دليل الحسابات موجود ({$existingCount} حساب) — لم يُستبدل");

            return;
        }

        $rows = require __DIR__.'/data/professional_chart_accounts.php';
        $service = app(AccountService::class);
        $byCode = [];

        DB::transaction(function () use ($tenantId, $rows, $service, &$byCode, $force) {
            foreach ($rows as $row) {
                [$code, $name, $nameEn, $type, $parentCode, $isGroup, $isSystem] = $row;
                if ($force && Account::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
                    $byCode[$code] = Account::where('tenant_id', $tenantId)->where('code', $code)->first();

                    continue;
                }
                $parent = $parentCode ? ($byCode[$parentCode] ?? Account::where('tenant_id', $tenantId)->where('code', $parentCode)->first()) : null;

                $account = $service->create($tenantId, [
                    'code' => $code,
                    'name' => $name,
                    'name_en' => $nameEn,
                    'type' => $type,
                    'parent_id' => $parent?->id,
                    'is_group' => $isGroup,
                    'is_postable' => ! $isGroup,
                    'is_system' => $isSystem,
                ]);

                $byCode[$code] = $account;
            }
        });

        $count = count($byCode);
        $this->command?->info("Professional chart of accounts ({$count} accounts) applied for tenant {$tenantId}");
    }
}
