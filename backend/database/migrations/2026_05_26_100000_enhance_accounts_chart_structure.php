<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'path')) {
                $table->string('path', 1000)->nullable()->after('level');
                $table->index(['tenant_id', 'path']);
            }
            if (! Schema::hasColumn('accounts', 'is_group')) {
                $table->boolean('is_group')->default(false)->after('is_postable');
            }
            if (! Schema::hasColumn('accounts', 'opening_balance')) {
                $table->decimal('opening_balance', 18, 4)->default(0)->after('currency');
            }
            if (! Schema::hasColumn('accounts', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('opening_balance');
            }
            if (! Schema::hasColumn('accounts', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('accounts', 'code')) {
            DB::statement('ALTER TABLE accounts MODIFY code VARCHAR(50) NOT NULL');
        }

        $this->backfillPathsAndGroups();
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'path')) {
                $table->dropIndex(['tenant_id', 'path']);
                $table->dropColumn('path');
            }
            if (Schema::hasColumn('accounts', 'is_group')) {
                $table->dropColumn('is_group');
            }
            if (Schema::hasColumn('accounts', 'opening_balance')) {
                $table->dropColumn('opening_balance');
            }
            if (Schema::hasColumn('accounts', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
            if (Schema::hasColumn('accounts', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });

        DB::statement('ALTER TABLE accounts MODIFY code VARCHAR(20) NOT NULL');
    }

    private function backfillPathsAndGroups(): void
    {
        $tenantIds = DB::table('accounts')->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $accounts = DB::table('accounts')
                ->where('tenant_id', $tenantId)
                ->orderBy('level')
                ->orderBy('code')
                ->get(['id', 'parent_id', 'code', 'is_postable']);

            $byId = $accounts->keyBy('id');

            foreach ($accounts as $row) {
                $path = $this->buildPath($row, $byId);
                $hasChildren = $accounts->contains(fn ($a) => (int) $a->parent_id === (int) $row->id);
                $isGroup = $hasChildren || ! (bool) $row->is_postable;

                DB::table('accounts')->where('id', $row->id)->update([
                    'path' => $path,
                    'is_group' => $isGroup,
                ]);
            }
        }
    }

    private function buildPath(object $row, $byId): string
    {
        $segments = [$row->code];
        $parentId = $row->parent_id;

        while ($parentId && $byId->has($parentId)) {
            $parent = $byId->get($parentId);
            array_unshift($segments, $parent->code);
            $parentId = $parent->parent_id;
        }

        return implode('/', $segments);
    }
};
