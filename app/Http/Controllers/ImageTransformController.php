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
        // 1. Find the image and verify that the user has permissions
        $imageRecord = Image::findOrFail($id);
        if ($imageRecord->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // 2.Validate the input for resize and crop. Use "sometimes" to allow both or just one to be sent.
        $request->validate([
            'transformations.resize.width'  => 'sometimes|required|integer|min:1',
            'transformations.resize.height' => 'sometimes|required|integer|min:1',
            'transformations.crop.width'    => 'sometimes|required|integer|min:1',
            'transformations.crop.height'   => 'sometimes|required|integer|min:1',
            'transformations.crop.x'        => 'sometimes|required|integer|min:0',
            'transformations.crop.y'        => 'sometimes|required|integer|min:0',
            'transformations.rotate'        => 'sometimes|required|numeric'
        ]);

        // 3. Download the image content from R2 and create the GD resource
        $contents = Storage::disk('r2')->get($imageRecord->path);
        $imageResource = imagecreatefromstring($contents);
        if (!$imageResource) {
            return response()->json(['error' => 'The image could not be processed'], 500);
        }

        // Variable where the modified resource will be stored
        $transformedImage = $imageResource;

        // 4. Apply resize transformation (if provided)
        if ($request->has('transformations.resize')) {
            $resizeWidth  = $request->input('transformations.resize.width');
            $resizeHeight = $request->input('transformations.resize.height');

            // Get original dimensions of the current image (can be the original or already transformed)
            $origWidth  = imagesx($transformedImage);
            $origHeight = imagesy($transformedImage);

            // Prevent upsize: do not allow the image to be enlarged
            $resizeWidth  = min($resizeWidth, $origWidth);
            $resizeHeight = min($resizeHeight, $origHeight);

            // Calculate the new dimension while maintaining the aspect ratio
            $ratioOrig = $origWidth / $origHeight;
            if (($resizeWidth / $resizeHeight) > $ratioOrig) {
                $resizeWidth = $resizeHeight * $ratioOrig;
            } else {
                $resizeHeight = $resizeWidth / $ratioOrig;
            }
            $resizeWidth  = (int) $resizeWidth;
            $resizeHeight = (int) $resizeHeight;

            // Create a resized temporary image
            $resizedImage = imagecreatetruecolor($resizeWidth, $resizeHeight);
            imagecopyresampled($resizedImage, $transformedImage, 0, 0, 0, 0, $resizeWidth, $resizeHeight, $origWidth, $origHeight);

            // Release the old resource if it will no longer be used (optional)
            if ($transformedImage !== $imageResource) {
                imagedestroy($transformedImage);
            }
            $transformedImage = $resizedImage;
        }

        // 5. Apply crop transformation (if provided)
        if ($request->has('transformations.crop')) {
            $cropData = $request->input('transformations.crop');
            $cropWidth  = $cropData['width'];
            $cropHeight = $cropData['height'];
            $cropX      = $cropData['x'];
            $cropY      = $cropData['y'];

            // The cropping area is defined
            $cropRect = [
                'x' => $cropX,
                'y' => $cropY,
                'width'  => $cropWidth,
                'height' => $cropHeight,
            ];

            // Apply crop using imagecrop
            $croppedImage = imagecrop($transformedImage, $cropRect);
            if ($croppedImage !== false) {
                // Release the previous resource if it is different
                imagedestroy($transformedImage);
                $transformedImage = $croppedImage;
            } else {
                return response()->json(['error' => 'The image could not be cropped. Check the crop settings..'], 500);
            }
        }

          // 6. Apply rotate transformation (if provided)
         if($request->has('transformations.rotate')) {
            $angle = $request->input('transformations.rotate');

          //A black background (0) is set for the uncovered areas and the image is flipped counterclockwise.
           $rotatedImage = Imagerotate($transformedImage, $angle, 0);

           if ($rotatedImage !== false)  { 
            imagedestroy($transformedImage);
            $transformedImage = $rotatedImage;
           } else {
         return response()->json(['error' => 'Could not rotate image' ], 500);
           }

        }
          


        // 7. Save the transformed image to a temporary file
        $newFilename = 'transformed_' . basename($imageRecord->path);
        $tempPath = sys_get_temp_dir() . '/' . $newFilename;
        imagejpeg($transformedImage, $tempPath, 90);

        // Free memory from GD resources
        imagedestroy($imageResource);
        imagedestroy($transformedImage);

        // 8. Upload the transformed image to Cloudflare R2
        $newPath = 'images/' . $newFilename;
        Storage::disk('r2')->put($newPath, file_get_contents($tempPath));

        // Delete the temporary file
        unlink($tempPath);

        // 9. Generate the URL and respond
        $url = Storage::disk('r2')->url($newPath);

        return response()->json([
            'message' => 'Correctly transformed image',
            'path'    => $newPath,
            'url'     => $url,
        ]);
    }
}
