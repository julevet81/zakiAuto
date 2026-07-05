<?php

namespace App\Exceptions;

use RuntimeException;

class BatchCarsImportFailedException extends RuntimeException
{
    /**
     * @param  array<int, array{row:int|null, errors: array<int, string>}>  $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'فشل استيراد الدفعة والسيارات. لم يتم إنشاء أي دفعة أو سيارات.'
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<int, array{row:int|null, errors: array<int, string>}>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
