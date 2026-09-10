<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    protected $fillable = ['target_url', 'normalized_domain', 'status', 'score', 'grade', 'result', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['result' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
