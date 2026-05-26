<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyDigestJob;
use App\Models\User;
use Illuminate\Console\Command;

class SendDigestEmailsCommand extends Command
{
    protected $signature = 'digest:send';
    protected $description = 'Send daily digest emails to users whose digest_hour matches the current UTC hour';

    public function handle(): int
    {
        $currentHour = now()->hour;
        $today       = now()->toDateString();

        $users = User::query()
            ->where('digest_enabled', true)
            ->where('digest_hour', $currentHour)
            ->where(function ($q) use ($today) {
                $q->whereNull('last_digest_sent')
                  ->orWhere('last_digest_sent', '<', $today);
            })
            ->get();

        $count = 0;
        foreach ($users as $user) {
            SendDailyDigestJob::dispatch($user);
            $count++;
        }

        $this->info("Dispatched digest jobs for {$count} user(s).");

        return self::SUCCESS;
    }
}
