<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiteSettingsController extends Controller
{
    private const RULES = [
        'website_name' => ['nullable', 'string', 'max:150'],
        'website_url' => ['nullable', 'url', 'max:255'],
        'website_description' => ['nullable', 'string', 'max:2000'],
        'default_language' => ['required', 'string', 'in:English,Filipino,Spanish'],
        'time_zone' => ['required', 'string', 'timezone'],
        'university_name' => ['nullable', 'string', 'max:150'],
        'official_address' => ['nullable', 'string', 'max:255'],
        'contact_number' => ['nullable', 'string', 'max:30'],
        'official_email' => ['nullable', 'email', 'max:150'],
        'facebook_url' => ['nullable', 'url', 'max:255'],
        'twitter_url' => ['nullable', 'url', 'max:255'],
        'logo_media_id' => ['nullable', 'integer', 'exists:media,media_id'],
    ];

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('site_settings')->pluck('setting_value', 'setting_key'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
        ]);
        $settings = $validated['settings'];
        $unknownKeys = array_diff(array_keys($settings), array_keys(self::RULES));

        if ($unknownKeys !== []) {
            throw ValidationException::withMessages([
                'settings' => ['Unsupported site setting: '.implode(', ', $unknownKeys)],
            ]);
        }

        $rules = [];
        foreach (self::RULES as $key => $rule) {
            $rules['settings.'.$key] = ['sometimes', ...$rule];
        }
        $request->validate($rules);

        DB::transaction(function () use ($settings): void {
            foreach ($settings as $key => $value) {
                $exists = DB::table('site_settings')->where('setting_key', $key)->exists();
                $values = [
                    'setting_value' => $value,
                    'updated_at' => now(),
                ];

                if ($exists) {
                    DB::table('site_settings')->where('setting_key', $key)->update($values);
                } else {
                    DB::table('site_settings')->insert($values + ['setting_key' => $key]);
                }
            }
        });

        return response()->json([
            'message' => 'Site settings saved.',
            'data' => DB::table('site_settings')->pluck('setting_value', 'setting_key'),
        ]);
    }
}
