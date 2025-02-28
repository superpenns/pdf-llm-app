<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessPdf;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class PdfController extends Controller
{
    public function store(Request $request)
    {

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = Storage::disk('public')->put('pdfs', $file);
        $originalNameWithTimestamp = time() . '_' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        ProcessPdf::dispatch($path, $originalNameWithTimestamp);

        return redirect()->route('pdf.show');
    }

    public function show()
    {

        $files = Storage::disk('public')->files('results');

        $pdfResults = [];
        foreach ($files as $file) {
            $pdfResults[] = [
                'name' => preg_replace('/\d+_/', '', pathinfo($file, PATHINFO_FILENAME)),
                'content' => Storage::disk('public')->get($file),
                'timestamp' => Storage::disk('public')->lastModified($file)
            ];
        }

        usort($pdfResults, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return Inertia::render('Home', [
            'pdfResults' => $pdfResults,
        ]);
    }
}
