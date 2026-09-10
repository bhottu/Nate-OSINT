<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialCorrelationScan extends Model
{
    protected $fillable = ['seed_platform', 'seed_username', 'seed_url', 'status', 'score', 'confidence', 'results'];

    protected function casts(): array
    {
        return ['results' => 'array'];
    }
}
