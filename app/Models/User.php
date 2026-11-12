<?php

namespace App\Models;

use KhattakBits\Core\Models\User as CoreUser;

/**
 * Host-side user model.
 *
 * Poora behaviour (casts, roles, password-reset notification, deleted-hook
 * detach) `KhattakBits\Core\Models\User` mein rehta hai — yeh class sirf woh
 * seam hai jahan product apni cheezein add kar sakta hai (relations, extra
 * traits, product-specific scopes) bina core ko fork kiye.
 *
 * `config/auth.php` isi class ko point karta hai, aur `Database\Factories\UserFactory`
 * bhi isi naam se resolve hoti hai (`App\Models\User` -> `Database\Factories\UserFactory`),
 * is liye `User::factory()` core mein move hone ke baad bhi kaam karta hai.
 */
class User extends CoreUser
{
    //
}
