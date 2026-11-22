<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                'banners' => $this->bannerRows($this->bannerQuery()->get()),
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
        // Check the raw input first: validate() silently drops keys it has no rule for.
        $submitted = $request->input('settings');
        if (is_array($submitted)) {
            $unknownKeys = array_diff(array_keys($submitted), self::SETTING_KEYS);
            if ($unknownKeys !== []) {
                throw ValidationException::withMessages([
                    'settings' => ['Unsupported Homepage setting: '.implode(', ', $unknownKeys)],
                ]);
            }
        }

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

        if (isset($settings['about_psu'])) {
            $settings['about_psu'] = $this->sanitizeRichText((string) $settings['about_psu']);
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
        return response()->json(['data' => $this->bannerRows($this->bannerQuery()->get())]);
    }

    /**
     * Create a banner. Mutations must be JSON (see CmsJsonMutations), so the
     * image arrives as a base64 data URL, like the Media Library upload.
     */
    public function addBanner(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:2000'],
            'button_label' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'string', 'max:2048', $this->urlRule()],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['required', 'string', 'max:7500000'],
        ]);

        $path = $this->storeBannerImage($validated['image']);
        try {
            $id = DB::table('homepage_banners')->insertGetId([
                'title' => trim($validated['title']),
                'subtitle' => $this->blankToNull($validated['subtitle'] ?? null),
                'image_path' => $path,
                'button_label' => $this->blankToNull($validated['button_label'] ?? null),
                'button_url' => $this->blankToNull($validated['button_url'] ?? null),
                'sort_order' => $validated['sort_order'] ?? ((int) DB::table('homepage_banners')->max('sort_order') + 1),
                'is_active' => $validated['is_active'] ?? true,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'banner_id');
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return response()->json([
            'message' => 'Banner created successfully.',
            'data' => $this->bannerRow($this->bannerQuery()->where('banner_id', $id)->first()),
        ], 201);
    }

    /** Update banner text/status/order and optionally replace its image. */
    public function updateBanner(Request $request, int $id)
    {
        $banner = DB::table('homepage_banners')->where('banner_id', $id)->first();
        abort_unless($banner, 404, 'Banner not found.');

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'button_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'button_url' => ['sometimes', 'nullable', 'string', 'max:2048', $this->urlRule()],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'nullable', 'string', 'max:7500000'],
        ]);

        $newImage = $validated['image'] ?? null;
        unset($validated['image']);
        foreach (['title', 'subtitle', 'button_label', 'button_url'] as $key) {
            if (array_key_exists($key, $validated)) {
                $validated[$key] = $this->blankToNull($validated[$key]);
            }
        }
        if (array_key_exists('title', $validated) && $validated['title'] === null) {
            throw ValidationException::withMessages(['title' => ['The title field is required.']]);
        }
        if ($newImage) {
            $validated['image_path'] = $this->storeBannerImage($newImage);
        }

        if ($validated !== []) {
            $validated['updated_at'] = now();
            try {
                DB::table('homepage_banners')->where('banner_id', $id)->update($validated);
            } catch (\Throwable $exception) {
                if (isset($validated['image_path'])) {
                    Storage::disk('public')->delete($validated['image_path']);
                }
                throw $exception;
            }
        }

        if (isset($validated['image_path']) && $banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }

        return response()->json([
            'message' => 'Banner updated successfully.',
            'data' => $this->bannerRow($this->bannerQuery()->where('banner_id', $id)->first()),
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

    /** Public banner image (banners are public website content). */
    public function bannerImage(int $id)
    {
        $banner = DB::table('homepage_banners')->where('banner_id', $id)->first(['image_path']);
        abort_unless($banner && $banner->image_path && Storage::disk('public')->exists($banner->image_path), 404);

        $stream = Storage::disk('public')->readStream($banner->image_path);
        abort_unless(is_resource($stream), 404);

        return response()->stream(static function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => Storage::disk('public')->mimeType($banner->image_path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }

    /** List published content that can be selected for a featured section. */
    public function getContentOptions(string $type)
    {
        abort_unless(in_array($type, ['news', 'announcements', 'events'], true), 404, 'Unsupported content type.');

        $query = match ($type) {
            'news' => DB::table('news')
                ->select('news_id as content_id', 'title', 'excerpt as description', 'published_at')
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
                'banners' => $this->bannerRows($this->bannerQuery()->where('is_active', true)->get()),
                'featured' => $this->featuredItems(true),
            ],
        ]);
    }

    private function bannerRows($rows)
    {
        return $rows->map(fn ($row) => $this->bannerRow($row))->values();
    }

    private function bannerRow(object $row): object
    {
        $row->is_active = (bool) $row->is_active;
        $row->image_url = $row->image_path ? route('homepage.banner-image', ['id' => $row->banner_id]) : null;
        unset($row->image_path);

        return $row;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    /** Allow only http(s) links, mailto links and site-relative paths. */
    private function urlRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $value = trim((string) $value);
            if ($value !== '' && ! preg_match('#^(https?://[^\s]+|mailto:[^\s]+|/[^\s]*|\#[^\s]*)$#i', $value)) {
                $fail('The button link must start with http://, https://, mailto:, or /.');
            }
        };
    }

    private function sanitizeRichText(string $html): string
    {
        $clean = preg_replace('#<(script|style|iframe|object|embed|template)\b.*?</\1\s*>#is', '', $html) ?? $html;
        $clean = strip_tags($clean, '<p><br><strong><b><em><i><u><ul><ol><li><a><div>');
        // Rebuild every <a> from scratch: keep only an http(s)/mailto href.
        $clean = preg_replace_callback('/<a\b([^>]*)>/i', static function (array $m): string {
            if (preg_match('/\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $m[1], $href)) {
                $url = html_entity_decode(trim($href[1] !== '' ? $href[1] : ($href[2] ?? '')));
                if (preg_match('#^(https?://[^\s<>"\']+|mailto:[^\s<>"\']+)$#i', $url)) {
                    return '<a href="'.e($url).'" rel="noopener noreferrer">';
                }
            }

            return '<a>';
        }, $clean) ?? $clean;
        // Drop every attribute from the remaining allowed tags.
        $clean = preg_replace('/<(p|br|strong|b|em|i|u|ul|ol|li|div)\b[^>]*>/i', '<$1>', $clean) ?? $clean;

        return trim($clean);
    }

    /** Validate, re-encode (strips payloads) and store a banner image; returns its public-disk path. */
    private function storeBannerImage(string $dataUrl): string
    {
        if (! preg_match('#^data:image/(jpeg|png|gif|webp);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $matches)) {
            throw ValidationException::withMessages(['image' => ['The banner image format is invalid.']]);
        }
        $bytes = base64_decode($matches[2], true);
        if ($bytes === false || strlen($bytes) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['image' => ['The banner image is invalid or exceeds 5 MB.']]);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $info = @getimagesizefromstring($bytes);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
            || $info === false || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 20000000) {
            throw ValidationException::withMessages(['image' => ['The banner image type or dimensions are not allowed.']]);
        }
        abort_unless(function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor'), 503, 'Image processing support is unavailable on this server.');

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw ValidationException::withMessages(['image' => ['The banner image cannot be decoded.']]);
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 1920 / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        match ($mime) {
            'image/jpeg' => imagejpeg($canvas, null, 85),
            'image/png' => imagepng($canvas),
            'image/gif' => imagegif($canvas),
            default => imagewebp($canvas, null, 85),
        };
        $encoded = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);
        abort_unless(is_string($encoded) && $encoded !== '', 422, 'The banner image could not be processed.');

        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
        $path = 'homepage/banners/'.Str::uuid().'.'.$extension;
        abort_unless(Storage::disk('public')->put($path, $encoded), 500, 'The banner image could not be stored.');

        return $path;
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
