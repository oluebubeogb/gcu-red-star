<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SponsorshipPackage extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['active' => 'boolean'];

    public function sponsorships() { return $this->hasMany(Sponsorship::class, 'package_id'); }
}
