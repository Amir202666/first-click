<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransferSqliteToMysql extends Command
{
    protected $signature = 'db:sqlite-to-mysql
                            {--fresh : تشغيل migrations على MySQL قبل النسخ (قاعدة فارغة)}';

    protected $description = 'نسخ البيانات من SQLite المحلي إلى MySQL (XAMPP) استعداداً للرفع للسيرفر';

    public function handle(): int
    {
        $sqlitePath = database_path('database.sqlite');
        if (! is_file($sqlitePath)) {
            $this->error('ملف SQLite غير موجود: '.$sqlitePath);

            return self::FAILURE;
        }

        config(['database.connections.sqlite_source' => [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        try {
            DB::connection('sqlite_source')->getPdo();
        } catch (\Throwable $e) {
            $this->error('فشل الاتصال بـ SQLite: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->error('فشل الاتصال بـ MySQL. أنشئ قاعدة البيانات في phpMyAdmin وعدّل backend/.env');
            $this->line('  DB_CONNECTION=mysql');
            $this->line('  DB_DATABASE=firstclick_local');
            $this->line('  DB_USERNAME=root');
            $this->line('  DB_PASSWORD=');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->info('تشغيل migrations على MySQL...');
            $this->call('migrate', ['--database' => 'mysql', '--force' => true]);
        }

        $tables = collect(DB::connection('sqlite_source')->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        ))->pluck('name')->all();

        if ($tables === []) {
            $this->warn('لا توجد جداول في SQLite.');

            return self::SUCCESS;
        }

        $this->info('جاري النسخ من SQLite إلى MySQL...');
        Schema::connection('mysql')->disableForeignKeyConstraints();

        $bar = $this->output->createProgressBar(count($tables));
        $bar->start();

        foreach ($tables as $table) {
            if (! Schema::connection('mysql')->hasTable($table)) {
                $bar->advance();

                continue;
            }

            DB::connection('mysql')->table($table)->truncate();

            DB::connection('sqlite_source')->table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($table) {
                $payload = $rows->map(fn ($r) => (array) $r)->all();
                if ($payload !== []) {
                    DB::connection('mysql')->table($table)->insert($payload);
                }
            });

            $bar->advance();
        }

        Schema::connection('mysql')->enableForeignKeyConstraints();
        $bar->finish();
        $this->newLine(2);
        $this->info('تم النسخ بنجاح. الخطوة التالية: scripts\\export-local-db.bat');

        return self::SUCCESS;
    }
}
