<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HomepageManagementController extends Controller
{
    /**
     * Fields exposed by the existing Homepage Content Editor.
     * Values are stored as key/value pairs to keep this feature isolated.
     */
    private const SETTING_KEYS = [
        'welcome_heading',
        'cta_button_label',
        'university_introduction',
        'homepage_description',
        'call_to_action_text',
        'featured_content',
        'about_psu',
    ];

    /** Return all Homepage Management data in one request. */
    public function index()
    {
        return response()->json([
            'data' => [
                'settings' => $this->settingsMap(),
                'banners' => $this->bannerQuery()->get(),
                'featured' => $this->featuredItems(),
            ],
        ]);
    }

    /** Return editable Homepage Content Editor fields. */
    public function getSettings()
    {
        return response()->json(['data' => $this->settingsMap()]);
    }

    /** Save only the known Homepage Content Editor fields. */
    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.welcome_heading' => ['sometimes', 'nullable', 'string', 'max:255'],
            'settings.cta_button_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'settings.university_introduction' => ['sometimes', 'nullable', 'string'],
            'settings.homepage_description' => ['sometimes', 'nullable', 'string'],
            'settings.call_to_action_text' => ['sometimes', 'nullable', 'string'],
            'settings.featured_content' => [
                'sometimes', 'nullable', 'string',
                Rule::in(['latest-news', 'latest-announcements', 'upcoming-events']),
            ],
            'settings.about_psu' => ['sometimes', 'nullable', 'string'],
        ]);

        $settings = $validated['settings'];
        $unknownKeys = array_diff(array_keys($settings), self::SETTING_KEYS);
        if ($unknownKeys !== []) {
            throw ValidationException::withMessages([
                'settings' => ['Unsupported Homepage setting: '.implode(', ', $unknownKeys)],
            ]);
        }

        DB::transaction(function () use ($settings) {
            foreach ($settings as $key => $value) {
                $exists = DB::table('homepage_settings')->where('setting_key', $key)->exists();
                $values = ['setting_value' => $value, 'updated_at' => now()];

                if ($exists) {
                    DB::table('homepage_settings')->where('setting_key', $key)->update($values);
                } else {
                    $values['created_at'] = now();
                    DB::table('homepage_settings')->insert($values + ['setting_key' => $key]);
                }
            }
        });

        return response()->json([
            'message' => 'Homepage content saved successfully.',
            'data' => $this->settingsMap(),
        ]);
    }

    /** List banners for the Banner Management page. */
    public function getBanners()
    {
        return response()->json(['data' => $this->bannerQuery()->get()]);
    }

    /** Create a banner and store its image on Laravel's public disk. */
    public function addBanner(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string'],
            'button_label' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $request->file('image')->store('homepage/banners', 'public');
        $id = DB::table('homepage_banners')->insertGetId([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'image_path' => $path,
            'button_label' => $validated['button_label'] ?? null,
            'button_url' => $validated['button_url'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'banner_id');

        return response()->json([
            'message' => 'Banner created successfully.',
            'data' => $this->bannerQuery()->where('banner_id', $id)->first(),
        ], 201);
    }

    /** Update banner text/status/order and optionally replace its image. */
    public function updateBanner(Request $request, int $id)
    {
        $banner = DB::table('homepage_banners')->where('banner_id', $id)->first();
        abort_unless($banner, 404, 'Banner not found.');

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string'],
            'button_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'button_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        unset($validated['image']);
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('homepage/banners', 'public');
        }

        if ($validated !== []) {
            $validated['updated_at'] = now();
            DB::table('homepage_banners')->where('banner_id', $id)->update($validated);
        }

        if (isset($validated['image_path']) && $banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }

        return response()->json([
            'message' => 'Banner updated successfully.',
            'data' => $this->bannerQuery()->where('banner_id', $id)->first(),
        ]);
    }

    /** Delete a banner and its stored image. */
    public function deleteBanner(int $id)
    {
        $banner = DB::table('homepage_banners')->where('banner_id', $id)->first();
        abort_unless($banner, 404, 'Banner not found.');

        DB::table('homepage_banners')->where('banner_id', $id)->delete();
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }

        return response()->json(['message' => 'Banner deleted successfully.']);
    }

    /** List published content that can be selected for a featured section. */
    public function getContentOptions(string $type)
    {
        abort_unless(in_array($type, ['news', 'announcements', 'events'], true), 404, 'Unsupported content type.');

        $query = match ($type) {
            'news' => DB::table('news')
                ->select('news_id as content_id', 'title', 'summary as description', 'published_at')
                ->where('status', 'published'),
            'announcements' => DB::table('announcements')
                ->select('announcement_id as content_id', 'title', 'content as description', 'published_at')
                ->where('status', 'published'),
            'events' => DB::table('events')
                ->select('event_id as content_id', 'title', 'description', 'start_at as published_at')
                ->where('status', 'published'),
        };

        return response()->json([
            'content_type' => $type,
            'data' => $query->orderByDesc('published_at')->limit(100)->get(),
        ]);
    }

    /** Return current featured selections with their existing content records. */
    public function getFeaturedItems()
    {
        return response()->json(['data' => $this->featuredItems()]);
    }

    /** Replace featured selections/order without duplicating News/Announcements/Events. */
    public function saveFeaturedItems(Request $request)
    {
        $validated = $request->validate([
            'items' => ['present', 'array'],
            'items.*.content_type' => ['required', Rule::in(['news', 'announcements', 'events'])],
            'items.*.content_id' => ['required', 'integer', 'min:1'],
            'items.*.section_title' => ['nullable', 'string', 'max:255'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.is_active' => ['sometimes', 'boolean'],
        ]);

        foreach ($validated['items'] as $index => $item) {
            [$table, $primaryKey] = $this->contentTableAndKey($item['content_type']);
            $exists = DB::table($table)
                ->where($primaryKey, $item['content_id'])
                ->where('status', 'published')
                ->exists();
            if (!$exists) {
                throw ValidationException::withMessages([
                    "items.$index.content_id" => ["Selected {$item['content_type']} record does not exist."],
                ]);
            }
        }

        DB::transaction(function () use ($validated) {
            DB::table('homepage_featured_items')->delete();

            foreach ($validated['items'] as $index => $item) {
                DB::table('homepage_featured_items')->insert([
                    'content_type' => $item['content_type'],
                    'content_id' => $item['content_id'],
                    'section_title' => $item['section_title'] ?? null,
                    'sort_order' => $item['sort_order'] ?? $index,
                    'is_active' => $item['is_active'] ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Featured sections saved successfully.',
            'data' => $this->featuredItems(),
        ]);
    }

    /** Public-facing Homepage payload for the website. */
    public function publicHomepage()
    {
        return response()->json([
            'data' => [
                'settings' => $this->settingsMap(),
                'banners' => $this->bannerQuery()->where('is_active', true)->get(),
                'featured' => $this->featuredItems(true),
            ],
        ]);
    }

    private function settingsMap(): array
    {
        return DB::table('homepage_settings')
            ->pluck('setting_value', 'setting_key')
            ->all();
    }

    private function bannerQuery()
    {
        return DB::table('homepage_banners')
            ->select('banner_id', 'title', 'subtitle', 'image_path', 'button_label', 'button_url', 'sort_order', 'is_active', 'created_at', 'updated_at')
            ->orderBy('sort_order')
            ->orderBy('banner_id');
    }

    private function featuredItems(bool $activeOnly = false)
    {
        $query = DB::table('homepage_featured_items')->orderBy('sort_order')->orderBy('featured_item_id');
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()->map(function ($item) {
            [$table, $primaryKey] = $this->contentTableAndKey($item->content_type);
            $item->content = DB::table($table)->where($primaryKey, $item->content_id)->first();
            return $item;
        });
    }

    private function contentTableAndKey(string $type): array
    {
        return match ($type) {
            'news' => ['news', 'news_id'],
            'announcements' => ['announcements', 'announcement_id'],
            'events' => ['events', 'event_id'],
            default => throw ValidationException::withMessages([
                'content_type' => ['Unsupported featured content type.'],
            ]),
        };
    }
}
