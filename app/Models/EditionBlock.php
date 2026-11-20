<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditionBlock extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    public function edition() { return $this->belongsTo(Edition::class); }
}
