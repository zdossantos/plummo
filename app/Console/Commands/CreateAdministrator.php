<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdministrator extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Create an administrator with interactive credentials';

    public function handle(): int
    {
        $name = $this->ask(__('admin.name'));
        $email = $this->ask(__('admin.email'));
        $password = $this->secret(__('admin.password_prompt'));
        $confirmation = $this->secret(__('admin.password_confirmation'));
        $validator = Validator::make([
            'name' => $name,
            'email' => is_string($email) ? mb_strtolower(trim($email)) : $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $values = $validator->validated();
        $user = new User;
        $user->fill($values);
        $user->forceFill(['is_admin' => true])->save();
        $this->info(__('admin.created'));

        return self::SUCCESS;
    }
}
