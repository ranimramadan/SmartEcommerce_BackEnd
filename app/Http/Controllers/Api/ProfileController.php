<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    // GET /api/profile
    public function show(Request $request)
    {
        $user = $request->user()->load('profile');

        if ($user->profile && $user->profile->profile_image) {
            $user->profile->profile_image_url = Storage::url($user->profile->profile_image);
        }

        return response()->json([
            'user'    => $user->only(['id', 'first_name', 'last_name', 'email']),
            'profile' => $user->profile,
        ]);
    }

    // PUT /api/profile
    public function update(Request $request)
    {
        $data = $request->validate([
            'phone_number'  => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'birthdate'     => 'nullable|date',
            'gender'        => 'nullable|string|in:male,female,other',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

        if ($request->hasFile('profile_image') && $request->file('profile_image')->isValid()) {
            if ($profile->profile_image && Storage::disk('public')->exists($profile->profile_image)) {
                Storage::disk('public')->delete($profile->profile_image);
            }
            $path = $request->file('profile_image')->store('profile_images', 'public');
            $data['profile_image'] = $path;
        }

        $profile->fill(Arr::except($data, ['profile_image']));

        if (array_key_exists('profile_image', $data)) {
            $profile->profile_image = $data['profile_image'];
        }

        $profile->save();

        $profile->profile_image_url = $profile->profile_image
            ? Storage::url($profile->profile_image)
            : null;

        Log::info('Profile updated', ['user_id' => $user->id]);

        return response()->json($profile);
    }
}
