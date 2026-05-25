<?php

namespace App\Console\Commands;

use Database\Seeders\SuperAdminSeeder;
use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create
                            {--email=admin@firstclickerp.com : البريد}
                            {--password=FirstClick@2026 : كلمة المرور}
                            {--name=مالك النظام : الاسم}
                            {--username=firstclick-admin : اسم المستخدم للدخول}
                            {--tenant= : معرف شركة واحدة (افتراضي: كل الشركات النشطة)}';

    protected $description = 'إنشاء Super Admin وربطه بكل الشركات (أو شركة محددة)';

    public function handle(): int
    {
        $tenantIds = null;
        if ($this->option('tenant')) {
            $tenantIds = [(int) $this->option('tenant')];
        }

        $this->info('جاري إنشاء / تحديث Super Admin...');

        (new SuperAdminSeeder)->setCommand($this)->run($tenantIds, [
            'name' => (string) $this->option('name'),
            'email' => (string) $this->option('email'),
            'username' => (string) $this->option('username'),
            'password' => (string) $this->option('password'),
        ]);

        return self::SUCCESS;
    }
}
