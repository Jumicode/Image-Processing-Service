<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class UploadController extends Controller
{
  
public function upload(Request $request)
{

// Validates that an image type file is received

$request->validate([
    'image' => 'required|image|max:2048',
]);

// Get the uploaded file

$file = $request->file('image');

// Save the image to disk "r2" inside the "images" folder

$path = $file->store('images','r2');

// // Get the URL of the uploaded file

$url = Storage::disk('r2')->url($path);

return response()->json([
    'message' => 'Image uploaded successfully',
    'path' => $path,
    'url' => $url,
]);

}

}
