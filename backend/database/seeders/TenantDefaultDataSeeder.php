<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ItemUnit;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\TenantSettingsService;
use Illuminate\Database\Seeder;

/**
 * بيانات افتراضية احترافية لكل شركة (بدون مسح البيانات التشغيلية الموجودة).
 */
class TenantDefaultDataSeeder extends Seeder
{
    public function run(?array $tenantIds = null, bool $forceChart = false): void
    {
        (new SubscriptionPlanSeeder)->run();
        (new RolesSeeder)->run();

        $tenants = $tenantIds
            ? Tenant::whereIn('id', $tenantIds)->get()
            : Tenant::all();

        foreach ($tenants as $tenant) {
            $this->command?->info("── {$tenant->name} (id: {$tenant->id}, {$tenant->slug}) ──");

            $hasAccounts = Account::where('tenant_id', $tenant->id)->exists();
            if (! $hasAccounts || $forceChart) {
                (new DefaultChartOfAccountsSeeder)->run($tenant->id);
                $this->command?->line('  ✓ دليل الحسابات الافتراضي');
            } else {
                $this->command?->line('  ○ دليل الحسابات موجود — لم يُستبدل');
            }

            if (Account::where('tenant_id', $tenant->id)->exists()) {
                (new TenantAccountDefaultsSeeder)->run($tenant->id);
                $this->command?->line('  ✓ ربط الحسابات الافتراضية للعمليات');
            }

            $this->seedCurrencies($tenant);
            $this->seedBranches($tenant);
            $this->seedPaymentMethods($tenant);
            $this->seedItemUnits($tenant);
            $this->seedSettings($tenant);
            $this->seedSubscription($tenant);

            $this->command?->newLine();
        }
    }

    private function seedCurrencies(Tenant $tenant): void
    {
        if (Currency::where('tenant_id', $tenant->id)->exists()) {
            $this->command?->line('  ○ العملات موجودة');

            return;
        }

        $code = $tenant->default_currency ?? 'SAR';
        $symbols = ['SAR' => 'ر.س', 'KWD' => 'د.ك', 'AED' => 'د.إ', 'USD' => '$'];

        Currency::create([
            'tenant_id' => $tenant->id,
            'code' => $code,
            'name' => $code === 'SAR' ? 'ريال سعودي' : $code,
            'symbol' => $symbols[$code] ?? $code,
            'decimal_places' => $code === 'KWD' ? 3 : 2,
            'exchange_rate' => 1,
            'base_currency' => $code,
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->command?->line('  ✓ العملة الافتراضية');
    }

    private function seedBranches(Tenant $tenant): void
    {
        if (Branch::where('tenant_id', $tenant->id)->exists()) {
            $this->command?->line('  ○ الفروع موجودة');

            return;
        }

        Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'address' => null,
            'phone' => null,
            'manager_name' => null,
            'is_active' => true,
        ]);

        $this->command?->line('  ✓ الفرع الرئيسي');
    }

    private function seedPaymentMethods(Tenant $tenant): void
    {
        if (PaymentMethod::where('tenant_id', $tenant->id)->exists()) {
            $this->command?->line('  ○ طرق الدفع موجودة');

            return;
        }

        $byCode = Account::where('tenant_id', $tenant->id)->get()->keyBy('code');

        PaymentMethod::create([
            'tenant_id' => $tenant->id,
            'name' => 'نقدي',
            'type' => 'cash',
            'linked_account_id' => $byCode->get('1111')?->id ?? $byCode->get('111')?->id,
            'is_active' => true,
        ]);

        PaymentMethod::create([
            'tenant_id' => $tenant->id,
            'name' => 'بنك',
            'type' => 'bank',
            'linked_account_id' => $byCode->get('1112')?->id ?? $byCode->get('112')?->id,
            'is_active' => true,
        ]);

        $this->command?->line('  ✓ طرق الدفع (نقدي + بنك)');
    }

    private function seedItemUnits(Tenant $tenant): void
    {
        if (ItemUnit::where('tenant_id', $tenant->id)->exists()) {
            $this->command?->line('  ○ وحدات القياس موجودة');

            return;
        }

        foreach ([
            ['name' => 'قطعة', 'symbol' => 'pc'],
            ['name' => 'كرتون', 'symbol' => 'box'],
            ['name' => 'كيلو', 'symbol' => 'kg'],
        ] as $unit) {
            ItemUnit::create([
                'tenant_id' => $tenant->id,
                'name' => $unit['name'],
                'symbol' => $unit['symbol'],
                'is_active' => true,
            ]);
        }

        $this->command?->line('  ✓ وحدات القياس');
    }

    private function seedSettings(Tenant $tenant): void
    {
        $settings = app(TenantSettingsService::class);
        $existing = $settings->getAll($tenant->id);

        $defaults = [
            'company_name' => $tenant->name,
            'default_currency' => $tenant->default_currency ?? 'SAR',
            'vat_enabled' => $tenant->vat_enabled ? '1' : '0',
            'vat_rate' => (string) ($tenant->vat_rate ?? 15),
            'fiscal_year_start' => '01-01',
            'invoice_prefix_sales' => 'Sal-',
            'invoice_prefix_purchase' => 'PUR-',
        ];

        $toSet = [];
        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $existing) || $existing[$key] === '' || $existing[$key] === null) {
                $toSet[$key] = $value;
            }
        }

        if ($toSet !== []) {
            $settings->setMany($tenant->id, $toSet);
            $this->command?->line('  ✓ الإعدادات الأساسية');
        } else {
            $this->command?->line('  ○ الإعدادات موجودة');
        }
    }

    private function seedSubscription(Tenant $tenant): void
    {
        $plan = SubscriptionPlan::where('slug', 'advanced')->first()
            ?? SubscriptionPlan::query()->orderByDesc('sort_order')->first();

        if (! $plan) {
            return;
        }

        Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id, 'status' => 'active'],
            [
                'subscription_plan_id' => $plan->id,
                'starts_at' => now(),
                'ends_at' => now()->create(2038, 1, 1, 0, 0, 0),
                'auto_renew' => true,
                'amount_paid' => 0,
                'currency' => $tenant->default_currency ?? 'SAR',
            ]
        );

        $this->command?->line('  ✓ اشتراك نشط حتى 2038');
    }
}
