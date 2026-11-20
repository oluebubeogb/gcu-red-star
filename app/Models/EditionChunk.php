<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditionChunk extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['embedding', 'embedding_json'];
    protected $casts = ['embedded_at' => 'datetime'];
    public function edition() { return $this->belongsTo(Edition::class); }
}
