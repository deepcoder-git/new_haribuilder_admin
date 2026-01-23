<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateFavicon extends Command
{
    protected $signature = 'favicon:generate {source?}';
    protected $description = 'Generate favicon files from source image';

    public function handle(): int
    {
        $sourcePath = $this->argument('source') 
            ?? base_path('resources/panel/assets/images/logo/favicon-source.png');

        if (!File::exists($sourcePath)) {
            $this->error("Source file not found: {$sourcePath}");
            $this->info("Please place your favicon source PNG file at:");
            $this->info("resources/panel/assets/images/logo/favicon-source.png");
            $this->info("\nOr specify a custom path:");
            $this->info("php artisan favicon:generate /path/to/your/icon.png");
            return Command::FAILURE;
        }

        $this->info("Generating favicons from: {$sourcePath}");
        
        $logoDir = base_path('resources/panel/assets/images/logo');
        $faviconDir = public_path('build/favicon_io');

        if (!File::isDirectory($faviconDir)) {
            File::makeDirectory($faviconDir, 0755, true);
        }

        $this->info("\nTo generate favicons, you can:");
        $this->info("1. Use online tool: https://realfavicongenerator.net/");
        $this->info("2. Use ImageMagick (if installed):");
        $this->info("   convert {$sourcePath} -resize 32x32 {$logoDir}/favicon.ico");
        $this->info("   convert {$sourcePath} -resize 16x16 {$faviconDir}/favicon-16x16.png");
        $this->info("   convert {$sourcePath} -resize 32x32 {$faviconDir}/favicon-32x32.png");
        $this->info("   convert {$sourcePath} -resize 180x180 {$faviconDir}/apple-touch-icon.png");
        $this->info("   convert {$sourcePath} -resize 192x192 {$faviconDir}/android-chrome-192x192.png");
        $this->info("   convert {$sourcePath} -resize 512x512 {$faviconDir}/android-chrome-512x512.png");

        $this->info("\nAfter generating, run: npm run build");

        return Command::SUCCESS;
    }
}

