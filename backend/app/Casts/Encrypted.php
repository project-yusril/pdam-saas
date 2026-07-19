<?php

namespace App\Casts;

use App\Support\SensitiveData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Encrypted implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?string
    {
        return SensitiveData::decrypt($value);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        return SensitiveData::encrypt($value);
    }
}
