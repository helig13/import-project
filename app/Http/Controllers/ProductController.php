<?php

namespace App\Http\Controllers;

use App\Imports\ProductsImport;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function uploadForm()
    {
        return view('upload');
    }

    public function import(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls', new \App\Rules\MaxFileSize
        ]);
        $import = new ProductsImport();
        Excel::import($import, $request->file('file'));

        return redirect()->back()->with('importResult', [
            'time' => $import->getProcessingTime(),
            'skipped' => $import->skippedRows,
            'duplicates' => $import->duplicates
        ]);

    }
}
