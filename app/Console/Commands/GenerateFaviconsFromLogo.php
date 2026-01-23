<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateFaviconsFromLogo extends Command
{
    protected $signature = 'favicon:generate-from-logo';
    protected $description = 'Generate all favicon files from logo.jpg';

    public function handle(): int
    {
        $logoPath = public_path('build/panel/images/logo/logo.jpg');
        
        if (!File::exists($logoPath)) {
            $this->error("Logo file not found: {$logoPath}");
            return Command::FAILURE;
        }

        $this->info("Generating favicons from: {$logoPath}");
        $this->info("Logo size: 1600x1600 JPEG");
        
        $logoDir = public_path('build/panel/images/logo');
        $faviconDir = public_path('build/favicon_io');
        $resourcesLogoDir = base_path('resources/panel/assets/images/logo');

        if (!File::isDirectory($faviconDir)) {
            File::makeDirectory($faviconDir, 0755, true);
        }

        $this->info("\n📋 Instructions to generate favicons:");
        $this->info("=====================================\n");
        
        $this->info("Option 1: Using ImageMagick (if installed):");
        $this->info("  convert {$logoPath} -resize 32x32 {$logoDir}/favicon.ico");
        $this->info("  convert {$logoPath} -resize 16x16 {$faviconDir}/favicon-16x16.png");
        $this->info("  convert {$logoPath} -resize 32x32 {$faviconDir}/favicon-32x32.png");
        $this->info("  convert {$logoPath} -resize 180x180 {$faviconDir}/apple-touch-icon.png");
        $this->info("  convert {$logoPath} -resize 192x192 {$faviconDir}/android-chrome-192x192.png");
        $this->info("  convert {$logoPath} -resize 512x512 {$faviconDir}/android-chrome-512x512.png");
        
        $this->info("\nOption 2: Using Online Tool (Recommended):");
        $this->info("  1. Go to: https://realfavicongenerator.net/");
        $this->info("  2. Upload: {$logoPath}");
        $this->info("  3. Configure settings and generate");
        $this->info("  4. Extract files to: {$faviconDir}/");
        $this->info("  5. Copy favicon.ico to: {$logoDir}/favicon.ico");
        
        $this->info("\nOption 3: Copy logo.jpg as temporary favicon:");
        $this->info("  cp {$logoPath} {$logoDir}/favicon-temp.jpg");
        $this->info("  (Then convert using online tool)");

        $this->info("\n✅ After generating, run: npm run build");
        $this->info("✅ Then clear browser cache to see new favicons");

        return Command::SUCCESS;
    }
}

