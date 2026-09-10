<?php

namespace App\Http\Controllers;

use App\Services\ExifExtractorService;
use Illuminate\Http\Request;

class ExifController extends Controller
{
    public function index()
    {
        return view('exif.index');
    }

    public function extract(Request $request, ExifExtractorService $extractor)
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/tiff', 'extensions:jpg,jpeg,png,tif,tiff'],
        ]);

        $file = $validated['image'];
        $metadata = $extractor->extract($file);
        $rows = $extractor->toRows($metadata);
        $preview = 'data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($file->getRealPath()));

        return view('exif.result', [
            'metadata' => $metadata,
            'rows' => $rows,
            'preview' => $preview,
            'hasExif' => count(array_filter($rows, fn ($row) => $row['value'] !== 'Not available')) > 0,
        ]);
    }

    public function privacy()
    {
        return view('exif.privacy');
    }
}
