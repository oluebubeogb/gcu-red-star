<?php
namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;

class DigestController extends Controller
{
    /** One-click unsubscribe from the signed link in the digest email. */
    public function unsubscribe(User $user)
    {
        $user->forceFill(['digest_opt_in' => false])->save();
        return Inertia::render('Public/Unsubscribed');
    }
}
