<?php

namespace App\Http\Controllers;

use App\Services\Media\MediaStorageService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(Request $request, MediaStorageService $media): StreamedResponse
    {
        try {
            $path = Crypt::decryptString($request->query('media', ''));
        } catch (DecryptException) {
            abort(404);
        }

        return $media->responseForPrivateMedia($path);
    }
}
