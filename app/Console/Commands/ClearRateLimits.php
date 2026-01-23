<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class ClearRateLimits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rate-limits:clear {--all : Clear all rate limiters}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear rate limiters for login attempts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Clearing rate limiters...');
        
        // Always clear all cache to ensure rate limiters are cleared
        try {
            Cache::flush();
            $this->info('✅ All cache cleared (including all rate limiters)');
        } catch (\Exception $e) {
            $this->warn('⚠️  Could not flush cache: ' . $e->getMessage());
        }
        
        // Also try to clear specific rate limiters for common IPs
        $commonIPs = ['127.0.0.1', '::1'];
        if (request()->has('ip')) {
            $commonIPs[] = request()->ip();
        }
        
        $cleared = 0;
        foreach ($commonIPs as $ip) {
            $keys = [
                "moderator-login-{$ip}",
                "moderator-reset-password-{$ip}",
            ];
            
            foreach ($keys as $key) {
                try {
                    if (RateLimiter::attempts($key) > 0) {
                        RateLimiter::clear($key);
                        $cleared++;
                        $this->line("   ✅ Cleared: {$key}");
                    }
                } catch (\Exception $e) {
                    // Ignore errors for specific keys
                }
            }
        }
        
        // Try to clear cache files directly if file-based cache
        if (config('cache.default') === 'file') {
            try {
                $cachePath = storage_path('framework/cache/data');
                if (is_dir($cachePath)) {
                    $files = glob($cachePath . '/*/*');
                    $deleted = 0;
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            try {
                                unlink($file);
                                $deleted++;
                            } catch (\Exception $e) {
                                // Ignore permission errors
                            }
                        }
                    }
                    if ($deleted > 0) {
                        $this->info("✅ Deleted {$deleted} cache file(s)");
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }
        
        $this->newLine();
        $this->info('✅ Rate limiters cleared! You can now login.');
        $this->line('');
        $this->line('💡 If you still see rate limit errors, try:');
        $this->line('   1. Wait 1-2 minutes');
        $this->line('   2. Clear your browser cache');
        $this->line('   3. Try from a different IP/network');
        
        return 0;
    }
}
