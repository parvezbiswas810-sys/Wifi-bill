<?php

namespace App\Models;

use App\Models\Concerns\BelongsToIsp;
use Illuminate\Database\Eloquent\Model;

class IspSetting extends Model
{
    use BelongsToIsp;

    protected $fillable = ['isp_id', 'key', 'value'];
}
