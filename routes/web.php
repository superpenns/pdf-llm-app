<?php

use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use OpenAI\Laravel\Facades\OpenAI;

/*Route::get('/', function () {
    return Inertia::render('Home');
});
Route::post('/', [PdfController::class, 'store']);
*/

Route::get('/', [PdfController::class, 'show'])->name('pdf.show');
Route::post('/', [PdfController::class, 'store'])->name('pdf.store');
