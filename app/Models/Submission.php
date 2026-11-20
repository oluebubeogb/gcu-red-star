<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['ai_review' => 'array', 'ai_reviewed_at' => 'datetime', 'is_demo' => 'boolean'];
    public function user() { return $this->belongsTo(User::class); }
}
