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
        ?string $message = null
    ) {
        if ($message === null) {
            $message = 'فشل استيراد الدفعة والسيارات. لم يتم إنشاء أي دفعة أو سيارات.';
            if (!empty($errors)) {
                $details = [];
                foreach ($errors as $err) {
                    $rowStr = isset($err['row']) ? "السطر {$err['row']}: " : "";
                    $errList = implode(', ', $err['errors'] ?? []);
                    $details[] = "{$rowStr}{$errList}";
                }
                $message .= ' التفاصيل: ' . implode(' | ', $details);
            }
        }
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
