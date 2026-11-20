<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Small key/value store for HQ settings (e.g. AI feature switches). */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public static function get(string $key, $default = null)
    {
        $all = Cache::remember('settings:all', 300, fn () => static::pluck('value', 'key')->all());
        return array_key_exists($key, $all) ? json_decode($all[$key], true) : $default;
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        Cache::forget('settings:all');
    }
}
