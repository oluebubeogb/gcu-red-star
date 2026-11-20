<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSummary extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['entities' => 'array'];
    public function edition() { return $this->belongsTo(Edition::class); }
}
