<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Image;

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
}
