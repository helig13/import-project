<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class MaxFileSize implements Rule
{
    protected $maxSize;

    public function __construct()
    {
        // Calculate the maximum allowable file size based on server settings.
        $uploadMaxFilesize = ini_get('upload_max_filesize');
        $postMaxSize = ini_get('post_max_size');
        $this->maxSize = min(
            $this->convertToBytes($uploadMaxFilesize),
            $this->convertToBytes($postMaxSize)
        );
    }

    public function passes($attribute, $value)
    {
        // Check if the uploaded file size is within the maximum allowable size.
        return $value->getSize() <= $this->maxSize;
    }

    public function message()
    {
        return "The uploaded file exceeds the maximum allowable size ({$this->maxSize} bytes).";
    }

    protected function convertToBytes($value)
    {
        $unit = strtoupper(substr($value, -1));
        $number = (int)substr($value, 0, -1);

        switch ($unit) {
            case 'K':
                return $number * 1024;
            case 'M':
                return $number * 1024 * 1024;
            case 'G':
                return $number * 1024 * 1024 * 1024;
            default:
                return $number;
        }
    }
}
