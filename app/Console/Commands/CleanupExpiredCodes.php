<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PasswordResetCode;
use Carbon\Carbon;

class CleanupExpiredCodes extends Command
{
    protected $signature = 'auth:cleanup-codes 
                           {--dry-run : Show what would be deleted without actually deleting}
                           {--older-than=10 : Delete codes older than X minutes (default: 10)}';
    
    protected $description = 'Remove expired password reset codes';

    public function handle()
    {
        $olderThan = $this->option('older-than');
        $dryRun = $this->option('dry-run');
        
        $cutoffTime = Carbon::now()->subMinutes($olderThan);
        
        $query = PasswordResetCode::where('expires_at', '<', $cutoffTime);
        
        if ($dryRun) {
            $count = $query->count();
            $this->info("Would delete {$count} expired password reset codes (dry run).");
            
            if ($count > 0) {
                $examples = $query->limit(5)->get(['email', 'expires_at', 'created_at']);
                $this->table(['Email', 'Expired At', 'Created At'], $examples->toArray());
            }
        } else {
            $deleted = $query->delete();
            $this->info("Deleted {$deleted} expired password reset codes.");
        }
        
        // Also clean up used codes older than 24 hours
        if (!$dryRun) {
            $usedDeleted = PasswordResetCode::where('is_used', true)
                ->where('updated_at', '<', Carbon::now()->subHours(24))
                ->delete();
                
            if ($usedDeleted > 0) {
                $this->info("Also deleted {$usedDeleted} old used codes.");
            }
        }
        
        return Command::SUCCESS;
    }
}