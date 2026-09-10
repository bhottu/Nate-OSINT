<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneScan extends Model
{
    protected $fillable = ['business_name', 'company_name', 'target_url', 'normalized_domain', 'status', 'result', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['result' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
