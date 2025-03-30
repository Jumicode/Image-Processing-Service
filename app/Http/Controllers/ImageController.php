<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
class ImageController extends Controller
{
    public function index()
    {
        // Gets only the images of the authenticated user
        $images = Auth::user()->images;

        return response()->json([
            'images' => $images,
        ]);
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
}
