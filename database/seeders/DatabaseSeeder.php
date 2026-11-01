<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /** Seeds an admin only when SEED_ADMIN_EMAIL + SEED_ADMIN_PASSWORD are set. Idempotent. */
    public function run(): void
    {
        $cfg = config('redstar.seed_admin');
        if (! $cfg['email'] || ! $cfg['password']) {
            $this->command?->info('SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD not set — skipping admin seed.');
            return;
        }
        $user = User::firstOrNew(['email' => strtolower($cfg['email'])]);
        $user->forceFill([
            'name' => $user->name ?: $cfg['name'],
            'username' => $user->username ?: strtolower($cfg['username']),
            'category' => 'admin',
            'role' => 'admin',
            'email_verified_at' => $user->email_verified_at ?: now(),
            'password' => $user->exists ? $user->password : Hash::make($cfg['password']),
        ])->save();
        $this->command?->info("Admin ready: {$user->email}");
    }
}
