<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibrarySetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
