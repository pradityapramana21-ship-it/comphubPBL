<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('user.profile.edit', ['user' => $request->user()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->profileAttributes())->save();

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function editPassword(Request $request): View
    {
        return view('user.profile.password', ['user' => $request->user()]);
    }

    public function updatePassword(ChangePasswordRequest $request, AuthService $auth): RedirectResponse
    {
        $auth->changePassword(
            user: $request->user(),
            currentPassword: (string) $request->input('current_password'),
            newPassword: (string) $request->input('password'),
        );

        return back()->with('success', 'Kata sandi berhasil diubah. Sesi di perangkat lain sudah dikeluarkan.');
    }
}
