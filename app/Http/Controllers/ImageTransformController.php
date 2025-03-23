<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class ImageTransformController extends Controller
{
    public function transform(Request $request, $id)
    {
        // Find the image and verify that the user has permissions
        $imageRecord = Image::findOrFail($id);
        if ($imageRecord->user_id !== Auth::id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Validate input: an object is expected "transformations" with "resize"
        $request->validate([
            'transformations.resize.width'  => 'required|integer|min:1',
            'transformations.resize.height' => 'required|integer|min:1',
        ]);

        $targetWidth  = $request->input('transformations.resize.width');
        $targetHeight = $request->input('transformations.resize.height');

        // Download the image content from Cloudflare R2
        $contents = Storage::disk('r2')->get($imageRecord->path);

        // Create image resource from content (supports JPEG, PNG, GIF, etc.)
        $srcImage = imagecreatefromstring($contents);
        if (!$srcImage) {
            return response()->json(['error' => 'No se pudo procesar la imagen'], 500);
        }

        // Get original dimensions
        $origWidth  = imagesx($srcImage);
        $origHeight = imagesy($srcImage);

        // Avoid upsize: new values ​​are not allowed to be larger than the original ones
        $targetWidth  = min($targetWidth, $origWidth);
        $targetHeight = min($targetHeight, $origHeight);

        // Calculate the new dimension while maintaining the aspect ratio
        $ratioOrig = $origWidth / $origHeight;
        if (($targetWidth / $targetHeight) > $ratioOrig) {
            $targetWidth = $targetHeight * $ratioOrig;
        } else {
            $targetHeight = $targetWidth / $ratioOrig;
        }
        // Convert to integers
        $targetWidth  = (int) $targetWidth;
        $targetHeight = (int) $targetHeight;

        // Create a blank image with the new dimensions
        $dstImage = imagecreatetruecolor($targetWidth, $targetHeight);

        // Resize the image
        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);

        // Save the resized image to a temporary file
        $newFilename = 'transformed_' . basename($imageRecord->path);
        $tempPath = sys_get_temp_dir() . '/' . $newFilename;
        // We use imagejpeg; if the image is of another type, it must be adjusted
        imagejpeg($dstImage, $tempPath, 90); // 90% Quality

        // Free memory
        imagedestroy($srcImage);
        imagedestroy($dstImage);

        // Upload the transformed image to Cloudflare R2 (optional: you can replace the original or save it separately)
        $newPath = 'images/' . $newFilename;
        Storage::disk('r2')->put($newPath, file_get_contents($tempPath));

        // Delete the temporary file
        unlink($tempPath);

        // Get the URL of the transformed image
        $url = Storage::disk('r2')->url($newPath);

        return response()->json([
            'message' => 'Correctly transformed image',
            'path'    => $newPath,
            'url'     => $url,
        ]);
    }
}
