<?php

namespace App\Imports;

use App\Events\ImportProgressUpdated;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rubric;
use App\Models\Subrubric;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Contracts\Queue\ShouldQueue;


class ProductsImport implements ToCollection, WithChunkReading, ShouldQueue
{
    use Importable;

    public $skippedRows = 0;
    public $duplicates = 0;
    public $totalFileRows = 0;
    public $startTime;
    public $timeTaken;


    public function __construct()
    {
        $this->startTime = now();
        Cache::put('totalFileRows', 0, now()->addMinutes(120));
        Cache::put('skippedRows', 0, now()->addMinutes(120));
        Cache::put('duplicates', 0, now()->addMinutes(120));

    }
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240', // 10 MB
        ]);

        $file = $request->file('file');
        if ($file->isValid()) {
            $path = $file->storeAs('uploads', $file->getClientOriginalName());
            $this->import($path);
        }
    }

    public function collection(Collection $rows)
    {
// Skipping the header row
        $rows = $rows->slice(1);
        $this->totalFileRows += $rows->count();
        foreach ($rows as $key => $row) {
            $rowArray = $row instanceof Collection ? $row->toArray() : $row;

            if (array_slice($rowArray, 0, 3) === [null, null, null]) {
                $this->skippedRows++;
                continue;
            }

            if (preg_match('/^[A-Za-z]+$/', $rowArray[5])) {
                $this->skippedRows++;
                continue;
            }
            $rows[$key] = collect($this->realignRowBasedOnModelCode($rowArray));
            // Shift the values if needed
            for ($i = 0; $i <= 3; $i++) {
                if (empty($rowArray[$i])) {
                    $rowArray[$i] = $rowArray[$i + 1];
                    $rowArray[$i + 1] = null;
                }
            }
        }
        // Check if the model_code consists of only letters and skip the row if it does

        //  Filter unique and valid rows
        $uniqueRows = $rows->unique(function ($row) {
            return $row[5];
        });

        $validRows = $uniqueRows->filter(function ($row) {
            return $this->isValidRowStructure($row);
        });


        $this->duplicates += ($rows->count() - $uniqueRows->count());

        $rubrics = Rubric::all()->keyBy('name');
        $subrubrics = Subrubric::all()->keyBy('name');
        $productCategories = ProductCategory::all()->keyBy('name');

        $existingProducts = Product::whereIn('model_code', $validRows->pluck(5))->get()->keyBy('model_code');
        $productsToInsert = [];

        foreach ($validRows as $row) {
            if (empty($row[0])) {
                $this->skippedRows++;
                continue;
            }
            // Check if the warranty is a number or null
            $warranty = is_numeric($row[8]) ? $row[8] : null;

            // Check if the availability is true or false based on the text
            $availability = strtolower($row[9]);
            if ($availability === 'есть в наличие') {
                $availability = true;
            } elseif ($availability === 'нет') {
                $availability = false;
            } else {
                // Invalid availability text, skip the row
                $this->skippedRows++;
                continue;
            }
            $manufacturerName = $row[3] ?? $row[4] ?? $row[5]; // Use the shorter of row 3 and row 4
            $manufacturerName = trim($manufacturerName); // Remove leading/trailing spaces
            if (strlen($manufacturerName) > 10) {
                $this->skippedRows++;
                continue;
            }
            $numericValue = $row[7];

// Check if the value is numeric and has less than 7 digits
            if (!is_numeric($numericValue) || strlen($numericValue) >= 7) {
                // If the value in $row[7] doesn't meet the criteria, check neighboring columns
                $leftNeighbor = $row[6]; // Value to the left
                $rightNeighbor = $row[8]; // Value to the right

                // Check if the left neighbor is numeric and has less than 7 digits
                if (is_numeric($leftNeighbor) && strlen($leftNeighbor) <= 7) {
                    $numericValue = $leftNeighbor;
                } elseif (is_numeric($rightNeighbor) && strlen($rightNeighbor) <= 7) {
                    $numericValue = $rightNeighbor;
                } else {
                    // If neither neighbor meets the criteria, skip the row
                    $this->skippedRows++;
                    continue;
                }
            }
            $rubric = $rubrics[$row[0]] ?? Rubric::firstOrCreate(['name' => $row[0]]);
            $subrubric = $subrubrics[$row[1]] ?? Subrubric::firstOrCreate(['name' => $row[1], 'rubric_id' => $rubric->id]);
            $productCategory = $productCategories[$row[2]] ?? ProductCategory::firstOrCreate(['name' => $row[2], 'rubric_id' => $rubric->id]);
            $manufacturer = Manufacturer::firstOrCreate(['name' => $manufacturerName]);
            if (isset($existingProducts[$row[5]])) {
                $this->skippedRows++;
                continue; // Skip if product already exists
            }

            $productsToInsert[] = [
                'category_id' => $productCategory->id,
                'manufacturer_id' => $manufacturer->id,
                'product_name' => $row[4],
                'model_code' => $row[5],
                'product_description' => $row[6],
                'retail_price' => $numericValue,
                'warranty' => $warranty,
                'availability' => $availability,
            ];
        }

        Product::insert($productsToInsert);
        Cache::increment('totalFileRowsCumulative', $this->totalFileRows);
        Cache::increment('skippedRowsCumulative', $this->skippedRows);
        Cache::increment('duplicatesCumulative', $this->duplicates);
        $this->finalizeImport();


    }

    protected function isValidRowStructure($row)
    {
        $nonNullableIndices = [5];

        foreach ($nonNullableIndices as $index) {
            if (is_null($row[$index]) || trim($row[$index]) === '') {
                return false;
            }
        }
        return true;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function getProcessingTime()
    {
        return now()->diffInSeconds($this->startTime);
    }

    protected function isShifted($row)
    {
        // Assuming that column 5 should always have a value
        // Adjust the logic if necessary.
        return is_null($row[5]) || trim($row[5]) === '';
    }

    public function finalizeImport()
    {
        $cumulativeTotalFileRows = Cache::get('totalFileRowsCumulative');
        $cumulativeSkippedRows = Cache::get('skippedRowsCumulative');
        $cumulativeDuplicates = Cache::get('duplicatesCumulative');
        $timeTaken = $this->getProcessingTime();

        broadcast(new ImportProgressUpdated(
            $this->totalFileRows,
            $this->skippedRows,
            $this->duplicates,
            $timeTaken,
            $cumulativeTotalFileRows,
            $cumulativeSkippedRows,
            $cumulativeDuplicates
        ));
        Cache::forget('totalFileRowsCumulative');
        Cache::forget('skippedRowsCumulative');
        Cache::forget('duplicatesCumulative');
    }
    protected function realignRowBasedOnModelCode($row) {
        $expectedIndex = 5;
        $pattern = '/^\d{5}$/';
        $currentIndex = null;
        foreach ($row as $index => $value) {
            if (preg_match($pattern, $value)) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            return $row; // No model code found, return the row as is.
        }

        // Check if it's shifted to the right
        if ($currentIndex > $expectedIndex) {
            while ($currentIndex != $expectedIndex) {
                array_unshift($row, null); // Add a null to the beginning of the array
                $currentIndex--;
            }
        }

        // Check if it's shifted to the left
        if ($currentIndex < $expectedIndex) {
            while ($currentIndex != $expectedIndex) {
                array_shift($row); // Remove the first element of the array
                $currentIndex++;
            }
        }

        return $row;
    }


}
