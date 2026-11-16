<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $user = User::firstOrNew(['email' => 'adminGestadd@gmail.com']);
        $user->name = $user->name ?: 'Admin Gestadd';
        $user->password = Hash::make('admin1234@');
        $user->role = User::ROLE_ADMIN;
        $user->is_active = true;
        $user->save();
    }

    public function down(): void
    {
        //
    }
};
