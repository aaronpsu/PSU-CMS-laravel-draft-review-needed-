<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiteSettingsController extends Controller
{
    private const LAYOUT_SECTIONS = ['banner', 'featured_news', 'announcements', 'events', 'about'];

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
        'header_layout' => ['nullable', 'string', 'in:logo-left,logo-center'],
        'footer_layout' => ['nullable', 'string', 'in:three-columns,two-columns,simple'],
        'menu_layout' => ['nullable', 'string', 'in:primary,compact'],
        'homepage_section_order' => ['sometimes', 'array', 'size:5'],
    ];

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->settingsMap(),
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
        if (array_key_exists('homepage_section_order', $settings)) {
            $rules['settings.homepage_section_order.*'] = [
                'required',
                'string',
                'distinct',
                'in:'.implode(',', self::LAYOUT_SECTIONS),
            ];
        }
        $request->validate($rules);

        DB::transaction(function () use ($settings): void {
            foreach ($settings as $key => $value) {
                $exists = DB::table('site_settings')->where('setting_key', $key)->exists();
                $values = [
                    'setting_value' => $key === 'homepage_section_order'
                        ? json_encode(array_values($value), JSON_THROW_ON_ERROR)
                        : $value,
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
            'data' => $this->settingsMap(),
        ]);
    }

    private function settingsMap(): array
    {
        $settings = DB::table('site_settings')->pluck('setting_value', 'setting_key')->all();
        if (isset($settings['homepage_section_order'])) {
            $order = json_decode((string) $settings['homepage_section_order'], true);
            if (is_array($order)) {
                $settings['homepage_section_order'] = $order;
            }
        }

        return $settings;
    }
}
