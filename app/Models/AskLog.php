<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AskLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['answered' => 'boolean', 'cached' => 'boolean'];
}
