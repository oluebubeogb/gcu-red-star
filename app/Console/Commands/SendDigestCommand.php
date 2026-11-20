<?php
namespace App\Console\Commands;

use App\Mail\MonthlyDigest;
use App\Support\Digest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDigestCommand extends Command
{
    protected $signature = 'redstar:digest {--dry-run : Only count recipients}';
    protected $description = 'Queue the monthly digest email to opted-in, verified members';

    public function handle(): int
    {
        $content = Digest::content();
        $n = 0;
        Digest::recipients()->chunkById(200, function ($users) use ($content, &$n) {
            foreach ($users as $u) {
                if (! $this->option('dry-run')) Mail::to($u)->queue(new MonthlyDigest($u, $content));
                $n++;
            }
        });
        $this->info(($this->option('dry-run') ? 'Would send' : 'Queued')." digest to {$n} members.");
        return self::SUCCESS;
    }
}
