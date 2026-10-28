<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait RejectsPersianDigits
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectPersianDigits($validator, $this->all());
        });
    }

    private function rejectPersianDigits(Validator $validator, array $input, string $prefix = ''): void
    {
        foreach ($input as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $this->rejectPersianDigits($validator, $value, $path);

                continue;
            }

            if (is_string($value) && preg_match('/[۰-۹٠-٩]/u', $value)) {
                $validator->errors()->add($path, 'ارقام باید انگلیسی باشند.');
            }
        }
    }
}
