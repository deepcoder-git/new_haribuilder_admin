<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Barryvdh\Snappy\Facades\SnappyImage;
use Illuminate\Support\Facades\Log;

class ImageZipController extends Controller
{
    public function generateZip(Request $request)
    {
        try {
            Log::info('Received request to generate ZIP');
            
            $htmlArray = $request->input('html_array', []);
            $zipName = $request->input('zip_name', 'student_ids_' . uniqid() . '.zip');
            
            if (empty($htmlArray)) {
                return response()->json(['error' => 'No HTML content provided'], 400);
            }

            // Create temp folder
            $tempFolder = storage_path('app/temp_zip_' . uniqid());
            if (!is_dir($tempFolder)) {
                mkdir($tempFolder, 0777, true);
            }

            Log::info('Processing ' . count($htmlArray) . ' HTML items');

            // Process each HTML content
            foreach ($htmlArray as $i => $html) {
                $imagePath = $tempFolder . '/student_' . ($i + 1) . '.jpg';
                
                try {
                    // Generate image from HTML using SnappyImage
                    SnappyImage::loadHTML($html)
                        ->setOption('enable-local-file-access', true)
                        ->setOption('format', 'jpg')
                        ->setOption('quality', 100)
                        ->setOption('width', 653)
                        ->setOption('height', 1023)
                        ->save($imagePath);
                    
                    Log::info('Generated image: ' . $imagePath);
                } catch (\Exception $e) {
                    Log::error('Failed to generate image for student ' . ($i + 1) . ': ' . $e->getMessage());
                    continue;
                }
            }

            // Create ZIP file
            $zipPath = storage_path('app/public/' . $zipName);
            $zip = new ZipArchive;
            
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                $imageFiles = glob($tempFolder . '/*.jpg');
                
                foreach ($imageFiles as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
                
                Log::info('ZIP created: ' . $zipPath);
            } else {
                throw new \Exception('Failed to create ZIP file');
            }

            // Clean up temp images
            array_map('unlink', glob($tempFolder . '/*.jpg'));
            @rmdir($tempFolder);

            // Return public URL to the generated ZIP file
            $publicUrl = url('storage/' . $zipName);
            
            Log::info('Returning ZIP URL: ' . $publicUrl);

      return redirect($publicUrl);
            
            // return response()->json([
            //     'zip_url' => $publicUrl,
            //     'status' => 'success',
            //     'message' => 'ZIP generated successfully'
            // ]);
            
        } catch (\Exception $e) {
            Log::error('Error in generateZip: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
} 