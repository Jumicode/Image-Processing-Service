<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Carbon\Carbon;


class ImageController extends Controller
{
    public function index(Request $request)
    {
    
        $limit = $request->query('limit', 10);
        
        // Get the paginated list of images from the authenticated user
        $images = Image::where('user_id', Auth::id())
                        ->paginate($limit);

        // Add the public URL of the image to each record
        $images->getCollection()->transform(function ($image) {
            $image->url = Storage::disk('r2')->url($image->path);
            return $image;
        });

        return response()->json($images);
        
    }

    public function show ($id)
    {
   // Search for the image by ID
    $image = Image::findOrFail($id);

     // Verify that the authenticated user is the owner of the image
    if ($image->user_id !== Auth::id()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    // Generate image URL from disk (Cloudflare R2)
    $url = Storage::disk('r2')->url($image->path);

   // Return image details
   return response() -> json([
  
   'id'         => $image->id,
   'user_id'    => $image->user_id,
   'path'       => $image->path,
   'url'        => $image->url,
   'created_at' => $image->created_at,
   'updated_at' => $image->updated_at,

   ]);

   }

 public function destroy ($id)
 {

$images = Image::where('user_id', Auth::id())->findOrFail($id);

Storage::disk('r2')->delete($images->path);

$images->delete();

return response()->json(['message' => 'Image deleted successfully']);

}
}
