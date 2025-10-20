<?php

namespace App\Console\Commands;

use App\Models\Purchase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupUnpaidPurchases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchases:cleanup-unpaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove unpaid purchases older than 5 hours with null payment_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of unpaid purchases...');

        try {
            // Calculate the cutoff time (5 hours ago)
            $cutoffTime = Carbon::now()->subHours(5);

            $purchasesToDelete = Purchase::where('payment_status', 'pending')
                ->where('created_at', '<=', $cutoffTime)
                ->get();

            if ($purchasesToDelete->isEmpty()) {
                $this->info('No unpaid purchases found to cleanup.');
                Log::info('Cleanup executed: No unpaid purchases found.');
                return 0;
            }

            $deletedCount = 0;
            $filesDeleted = 0;

            DB::beginTransaction();

            try {
                foreach ($purchasesToDelete as $purchase) {
                    // Delete associated files
                    $filesDeleted += $this->deleteAssociatedFiles($purchase);

                    // Delete the purchase record
                    $purchase->delete();
                    $deletedCount++;

                    $this->line("Deleted purchase ID: {$purchase->id} (created at: {$purchase->created_at})");
                }

                DB::commit();

                $this->info("✓ Successfully deleted {$deletedCount} unpaid purchase(s)");
                $this->info("✓ Cleaned up {$filesDeleted} associated file(s)");

                Log::info('Unpaid purchases cleanup completed', [
                    'deleted_count' => $deletedCount,
                    'files_deleted' => $filesDeleted,
                    'cutoff_time' => $cutoffTime->toDateTimeString(),
                ]);

                return 0;

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            $this->error('Error during cleanup: ' . $e->getMessage());
            Log::error('Unpaid purchases cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Delete files associated with a purchase
     *
     * @param Purchase $purchase
     * @return int Number of files deleted
     */
    private function deleteAssociatedFiles(Purchase $purchase): int
    {
        $filesDeleted = 0;

        try {
            // Delete logo catalogue files
            if ($purchase->logo_catalogue && is_array($purchase->logo_catalogue)) {
                foreach ($purchase->logo_catalogue as $logoPath) {
                    if (Storage::disk('public')->exists($logoPath)) {
                        Storage::disk('public')->delete($logoPath);
                        $filesDeleted++;
                    }
                }
            }

            // Delete product document
            if ($purchase->product_document && Storage::disk('public')->exists($purchase->product_document)) {
                Storage::disk('public')->delete($purchase->product_document);
                $filesDeleted++;
            }

        } catch (\Exception $e) {
            Log::warning('Error deleting files for purchase', [
                'purchase_id' => $purchase->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $filesDeleted;
    }

}
