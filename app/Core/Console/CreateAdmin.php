<?php

namespace App\Core\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email} {--name=Super Admin}';

    protected $description = 'Super admin banayen ya uska password badlein';

    public function handle(): int
    {
        $password = (string) $this->secret('Password (kam az kam 10 characters)');
        if (strlen($password) < 10) {
            $this->error('Password kam az kam 10 characters ka ho.');

            return self::FAILURE;
        }

        Role::findOrCreate('super-admin', 'web');   // saaf install par seeder ke baghair bhi chale

        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            ['name' => $this->option('name'), 'password' => $password]   // 'hashed' cast khud hash karta hai
        );
        $user->forceFill(['is_active' => true])->save();
        $user->assignRole('super-admin');

        $this->info("Super admin tayyar: {$user->email}");

        return self::SUCCESS;
    }
}
