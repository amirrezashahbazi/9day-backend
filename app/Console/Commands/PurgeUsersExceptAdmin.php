<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeUsersExceptAdmin extends Command
{
    private const KEEP_MOBILE = '09194949534';

    protected $signature = 'users:purge {--force : بدون پرسش تایید حذف کند}';

    protected $description = 'همه کاربران را حذف می‌کند و فقط ادمین 09194949534 را نگه می‌دارد';

    public function handle(): int
    {
        $keep = User::withTrashed()->where('mobile', self::KEEP_MOBILE)->first();

        if (! $keep) {
            $this->error('کاربر '.self::KEEP_MOBILE.' پیدا نشد. هیچ کاربری حذف نشد.');

            return self::FAILURE;
        }

        $ids = User::withTrashed()->where('id', '!=', $keep->id)->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('کاربر دیگری برای حذف نیست.');

            return self::SUCCESS;
        }

        $count = $ids->count();

        if (! $this->option('force') && ! $this->confirm("{$count} کاربر حذف شود و فقط ".self::KEEP_MOBILE.' بماند؟')) {
            $this->warn('لغو شد.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($ids): void {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $ids)
                ->delete();

            DB::table('sessions')->whereIn('user_id', $ids)->delete();

            User::withTrashed()->whereIn('id', $ids)->get()->each->forceDelete();
        });

        $this->info("{$count} کاربر حذف شد. ".self::KEEP_MOBILE.' باقی ماند.');

        return self::SUCCESS;
    }
}
