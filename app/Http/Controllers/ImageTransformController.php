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
            'transformations.rotate'        => 'sometimes|required|numeric',
            'transformations.format'        => 'sometimes|required|string',
            'transformations.filters.grayscale' => 'sometimes|required|boolean',
            'transformations.filters.sepia'     => 'sometimes|required|boolean',
            'transformations.compress'          => 'sometimes|required|integer|min:0|max:100',
            'transformations.watermark.image'   => 'sometimes|required|url',
            'transformations.watermark.x'       => 'sometimes|required|integer',
            'transformations.watermark.y'       => 'sometimes|required|integer',
            'transformations.watermark.opacity' => 'sometimes|required|integer|min:0|max:100'
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
  
// 7. Apply filters transformation (grayscale and sepia)

        if ($request->has('transformations.filters')) {
            $filters = $request->input('transformations.filters');
            // If sepia is requested, grayscale is applied first and then colorize to simulate the sepia effect.
            if (isset($filters['sepia']) && $filters['sepia']) {
                imagefilter($transformedImage, IMG_FILTER_GRAYSCALE);
                imagefilter($transformedImage, IMG_FILTER_COLORIZE, 90, 60, 40);
            } elseif (isset($filters['grayscale']) && $filters['grayscale']) {
                imagefilter($transformedImage, IMG_FILTER_GRAYSCALE);
            }
        }

        if ($request->has('transformations.watermark')) {
            $wmData = $request->input('transformations.watermark');
            $watermarkUrl = $wmData['image'];
            $watermarkX   = $wmData['x'];
            $watermarkY   = $wmData['y'];
            $opacity      = $wmData['opacity']; // Value between 0 and 100

           // Load the watermark image
            $wmContents = file_get_contents($watermarkUrl);
            if ($wmContents === false) {
                return response()->json(['error' => 'The watermark image could not be loaded..'], 500);
            }
            $watermarkResource = imagecreatefromstring($wmContents);
            if (!$watermarkResource) {
                return response()->json(['error' => 'The watermark image could not be loaded..'], 500);
            }

            // Get watermark dimensions
            $wmWidth  = imagesx($watermarkResource);
            $wmHeight = imagesy($watermarkResource);

            // Apply the watermark to the transformed image using imagecopymerge()
// imagecopymerge() expects the opacity in percentage (where 100 is opaque and 0 is transparent)
            imagecopymerge($transformedImage, $watermarkResource, $watermarkX, $watermarkY, 0, 0, $wmWidth, $wmHeight, $opacity);
            imagedestroy($watermarkResource);
        }

    // 8. Apply format transformation (if provided)
   // If not specified, JPEG will be used by default.   
        $format = 'jpeg';
        if ($request->has('transformations.format')) {
            $requestedFormat = strtolower($request->input('transformations.format'));
            $allowedFormats = ['jpeg', 'jpg', 'png', 'gif'];
            if (!in_array($requestedFormat, $allowedFormats)) {
                return response()->json(['error' => 'Unsupported format.'], 400);
            }
            // We consider "jpg" as "jpeg"
            $format = $requestedFormat === 'jpg' ? 'jpeg' : $requestedFormat;
        }

    // 9. Get compression value (if sent, otherwise use default value)
    $compressQuality = 90;
    if ($request->has('transformations.compress')) {
        $compressQuality = $request->input('transformations.compress');
    }

    // 10. Save the transformed image to a temporary file, applying compression
    $newFilename = 'transformed_' . pathinfo($imageRecord->path, PATHINFO_FILENAME) . '.' . $format;
    $tempPath    = sys_get_temp_dir() . '/' . $newFilename;

    switch ($format) {
        case 'jpeg':
            imagejpeg($transformedImage, $tempPath, $compressQuality);
            break;
        case 'png':
            // For PNGs, compression is a level between 0 (no compression) and 9 (maximum compression).
             // Convert the value from 0-100 to 0-9 (where 100 = 0 and 0 = 9).
            $pngCompression = (int) round((100 - $compressQuality) * 9 / 100);
            imagepng($transformedImage, $tempPath, $pngCompression);
            break;
        case 'gif':
            imagegif($transformedImage, $tempPath);
            break;
        default:
            return response()->json(['error' => 'Unsupported format.'], 400);
    }


        // Free memory from GD resources
        imagedestroy($imageResource);
        imagedestroy($transformedImage);

        // 11. Upload the transformed image to Cloudflare R2
        $newPath = 'images/' . $newFilename;
        Storage::disk('r2')->put($newPath, file_get_contents($tempPath));

        // Delete the temporary file
        unlink($tempPath);

        // 12. Generate the URL and respond
        $url = Storage::disk('r2')->url($newPath);

        return response()->json([
            'message' => 'Correctly transformed image',
            'path'    => $newPath,
            'url'     => $url,
        ]);
    }
}
