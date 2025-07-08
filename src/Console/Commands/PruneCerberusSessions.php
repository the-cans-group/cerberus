<?php

namespace Cerberus\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneCerberusSessions extends Command
{
    protected $signature = 'cerberus:prune';
    protected $description = 'Clear expired or revoked Cerberus sessions';

    public function handle(): int
    {
        $count = DB::table('cerberus_user_device_sessions')
            ->where(function ($query) {
                $query->where('expires_at', '<', now())
                    ->orWhereNotNull('revoked_at');
            })
            ->delete();

        $this->info("{$count} expired or revoked Cerberus session(s) deleted.");
        return self::SUCCESS;
    }
}
