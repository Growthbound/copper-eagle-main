<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class ManageAdminAccount extends Command
{
    protected $signature = 'admin:account
                            {--email= : The account email address}
                            {--name= : The display name, used when creating a new account}
                            {--password= : Skip the prompt; useful on hosts without an interactive console}';

    protected $description = 'Create the site owner account, or reset the password on an existing one';

    public function handle(): int
    {
        $email = $this->option('email') ?: text(
            label: 'Email address',
            required: true,
        );

        $existing = User::query()->where('email', $email)->first();

        $name = $existing?->name ?: ($this->option('name') ?: text(
            label: 'Name',
            default: 'Site Owner',
            required: true,
        ));

        $secret = $this->option('password') ?: password(
            label: $existing ? 'New password' : 'Password',
            required: true,
        );

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $secret],
            [
                'email' => 'required|email',
                'name' => 'required|string|max:120',
                'password' => 'required|string|min:12',
            ],
            ['password.min' => 'Use at least 12 characters.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $secret],
        );

        $this->components->info($existing
            ? "Password updated for {$email}."
            : "Admin account created for {$email}.");

        return self::SUCCESS;
    }
}
