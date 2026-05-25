<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class DiagnoseLogin extends Command
{
    protected $signature = 'admin:diagnose-login
                            {company : slug الشركة مثل first-company}
                            {username : البريد أو اسم المستخدم}';

    protected $description = 'فحص سبب فشل تسجيل الدخول (شركة + مستخدم + ربط + كلمة المرور)';

    public function handle(): int
    {
        $slug = trim((string) $this->argument('company'));
        $login = trim((string) $this->argument('username'));

        $tenant = Tenant::where('slug', $slug)->first();
        if (! $tenant) {
            $this->error("❌ الشركة «{$slug}» غير موجودة.");

            return self::FAILURE;
        }

        $this->info("✓ الشركة: {$tenant->name} (id={$tenant->id}, active=".($tenant->is_active ? 'yes' : 'no').')');

        $user = User::query()
            ->where(function ($q) use ($login) {
                $q->where('username', $login)->orWhere('email', $login);
            })
            ->first();

        if (! $user) {
            $this->error("❌ المستخدم «{$login}» غير موجود في جدول users.");
            $this->line('   جرّب: php artisan admin:create');
            $this->line('   أو الدخول بـ admin@firstclick.com / password123 إن وُجد DemoDataSeeder.');

            return self::FAILURE;
        }

        $this->info("✓ المستخدم: id={$user->id}, email={$user->email}, username={$user->username}");
        $this->info('  is_super_admin: '.($user->is_super_admin ? 'yes' : 'no'));

        $linked = $user->tenants()->where('tenants.id', $tenant->id)->exists();
        if ($linked) {
            $pivot = $user->tenants()->where('tenants.id', $tenant->id)->first()->pivot;
            $this->info('✓ مربوط بالشركة (pivot is_active='.($pivot->is_active ? 'yes' : 'no').')');
        } elseif ($user->is_super_admin) {
            $this->warn('⚠ Super Admin غير مربوط بهذه الشركة — شغّل: php artisan admin:create');
        } else {
            $this->error('❌ المستخدم غير مربوط بهذه الشركة في tenant_users.');
        }

        $testPasswords = [
            'FirstClick@2026',
            'password123',
            'FirstClickERP',
        ];
        $this->newLine();
        $this->info('اختبار كلمات مرور شائعة (أدخل يدوياً إن لم تطابق):');
        foreach ($testPasswords as $plain) {
            $ok = Hash::check($plain, (string) $user->password);
            $this->line('  '.($ok ? '✓' : '✗')." {$plain}");
        }

        return self::SUCCESS;
    }
}
