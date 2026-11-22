<?php

namespace App\Http\Controllers;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/dashboard
 *
 * Read-only aggregates for the admin dashboard. All figures come from cms_db;
<<<<<<< HEAD
 * nothing is cached or invented. Targets the psu_cms_admin schema. Nothing writes
 * to activity_logs yet, so "recent activity" is derived from created_at/updated_at
 * and created_by (see recentActivity()).
=======
 * nothing is cached or invented. The schema has no audit-log table, so "recent
 * activity" is derived from created_at/updated_at and the available creator
 * references in the PSU schema.
>>>>>>> baebe517c5a8372fbe02ff7bf16ebdc652c8454a
 */
class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Use the database clock for everything so "this year" and "x min ago"
        // agree with the stored timestamps regardless of PHP/app timezone.
        $year = (int) DB::scalar('SELECT YEAR(CURDATE())');

        return response()->json([
            'cards'           => $this->safe('cards', fn () => $this->cards(), []),
            'chart'           => ['year' => $year, 'series' => $this->safe('chart', fn () => $this->chartSeries($year), [])],
            'recentPublished' => $this->safe('recentPublished', fn () => $this->recentPublished(), []),
            'recentActivity'  => $this->safe('recentActivity', fn () => $this->recentActivity(), []),
        ]);
    }

    /** One broken section must not blank the whole dashboard; log it and return a fallback. */
    private function safe(string $section, \Closure $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            report($e);
            logger()->error("Dashboard section '{$section}' failed: " . $e->getMessage());
            return $fallback;
        }
    }

    private function cards(): array
    {
        return [
            'programs'       => DB::table('programs')->where('status', 'active')->count(),
            'news'           => DB::table('news')->where('status', 'published')->count(),
            'announcements'  => DB::table('announcements')->where('status', 'published')->count(),
            'upcomingEvents' => DB::table('events')
                ->where('status', 'published')
                ->where('start_at', '>=', DB::raw('NOW()'))
                ->count(),
        ];
    }

    /** Twelve monthly counts (Jan..Dec) per content type for the given year. */
    private function chartSeries(int $year): array
    {
        return [
            'news'          => $this->monthly('news', 'published_at', $year, fn ($q) => $q->where('status', 'published')),
            'announcements' => $this->monthly('announcements', 'published_at', $year, fn ($q) => $q->where('status', 'published')),
            'events'        => $this->monthly('events', 'published_at', $year, fn ($q) => $q->whereIn('status', ['published', 'completed'])),
            'documents'     => $this->monthly('documents', 'created_at', $year),
            'other'         => $this->monthly('media', 'created_at', $year),
        ];
    }

    /** $table/$column are hard-coded by the callers above, never request input. */
    private function monthly(string $table, string $column, int $year, ?Closure $scope = null): array
    {
        $query = DB::table($table)
            ->selectRaw("MONTH({$column}) as m, COUNT(*) as c")
            ->where($column, '>=', "{$year}-01-01")
            ->where($column, '<', ($year + 1) . '-01-01');
        if ($scope) {
            $scope($query);
        }

        $counts = array_fill(0, 12, 0);
        foreach ($query->groupBy(DB::raw("MONTH({$column})"))->get() as $row) {
            $counts[(int) $row->m - 1] = (int) $row->c;
        }

        return $counts;
    }

    private function recentPublished(): array
    {
        $news = DB::table('news as n')
            ->leftJoin('media as m', 'm.media_id', '=', 'n.cover_media_id')
            ->where('n.status', 'published')
            ->whereNotNull('n.published_at')
            ->select([
                DB::raw("'news' as type"), 'n.news_id as id', 'n.title',
                'n.published_at as published_on', 'm.media_id as media_id',
            ]);

        $announcements = DB::table('announcements as a')
            ->where('a.status', 'published')
            ->whereNotNull('a.published_at')
            ->select([
                DB::raw("'announcement' as type"), 'a.announcement_id as id', 'a.title',
                'a.published_at as published_on', DB::raw('NULL as media_id'),
            ]);

        $events = DB::table('events as e')
            ->leftJoin('media as m', 'm.media_id', '=', 'e.cover_media_id')
            ->where('e.status', 'published')
            ->select([
                DB::raw("'event' as type"), 'e.event_id as id', 'e.title',
                DB::raw('COALESCE(e.published_at, e.created_at) as published_on'), 'm.media_id as media_id',
            ]);

        return DB::query()
            ->fromSub($news->unionAll($announcements)->unionAll($events), 'items')
            ->orderByDesc('published_on')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'type'      => $row->type,
                'id'        => (int) $row->id,
                'title'     => $row->title,
                'date'      => substr((string) $row->published_on, 0, 10),
                'thumbnail' => $row->media_id ? route('cms.media', ['id' => $row->media_id]) : null,
            ])
            ->all();
    }

    /**
     * Newest row changes across the main content tables. "Added" vs "Edited" is
     * inferred from updated_at differing from created_at; the actor is the
     * creator (created_by) because psu_cms_admin does not track who edited.
     */
    private function recentActivity(): array
    {
        // psu_cms_admin has no updated_by column and no audit rows are written to
        // activity_logs, so the actor is the record's creator (created_by).
        $sources = [
<<<<<<< HEAD
            ['news', 'title', 'news', 't.created_by', 't.status', 't.updated_at'],
            ['announcements', 'title', 'announcement', 't.created_by', 't.status', 't.updated_at'],
            ['events', 'title', 'event', 't.created_by', 't.status', 't.updated_at'],
            ['faculty_staff', "TRIM(CONCAT_WS(' ', t.first_name, t.last_name))", 'personnel', 'NULL', 'NULL', 't.updated_at'],
            ['documents', 'COALESCE(t.title, t.file_name)', 'document', 't.created_by', 'NULL', 't.created_at'],
=======
            ['news', 'news_id', 'title', 'news', 't.created_by', 't.status', 't.updated_at'],
            ['announcements', 'announcement_id', 'title', 'announcement', 't.created_by', 't.status', 't.updated_at'],
            ['events', 'event_id', 'title', 'event', 't.created_by', 't.status', 't.updated_at'],
            ['faculty_staff', 'faculty_id', "CONCAT_WS(' ', NULLIF(t.first_name, ''), NULLIF(t.middle_name, ''), NULLIF(t.last_name, ''))", 'personnel', 'NULL', 't.status', 't.updated_at'],
            ['documents', 'document_id', 'COALESCE(t.title, t.file_name)', 'document', 't.created_by', 'NULL', 't.created_at'],
>>>>>>> baebe517c5a8372fbe02ff7bf16ebdc652c8454a
        ];

        $union = null;
        foreach ($sources as [$table, $titleExpr, $kind, $actorExpr, $statusExpr, $tsColumn]) {
            $title = str_contains($titleExpr, '(') ? $titleExpr : "t.{$titleExpr}";
            $edited = $tsColumn === 't.updated_at' ? 'TIMESTAMPDIFF(SECOND, t.created_at, t.updated_at)' : '0';

            // Everything interpolated here comes from the constant list above.
            $query = DB::table("{$table} as t")
                ->leftJoin('users as u', 'u.user_id', '=', DB::raw($actorExpr))
                ->select([
                    DB::raw("'{$kind}' as kind"),
                    DB::raw("{$title} as title"),
                    'u.full_name as actor',
                    DB::raw("{$statusExpr} as status"),
                    DB::raw("{$tsColumn} as ts"),
                    DB::raw("{$edited} as edited_after"),
                    DB::raw("TIMESTAMPDIFF(SECOND, {$tsColumn}, NOW()) as age_seconds"),
                    DB::raw("DATE({$tsColumn}) as day"),
                ]);
            $union = $union ? $union->unionAll($query) : $query;
        }

        return DB::query()
            ->fromSub($union, 'activity')
            ->orderByDesc('ts')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'user'       => $row->actor,
                'activity'   => $this->activityLabel($row),
                'content'    => $row->title,
                'ageSeconds' => max(0, (int) $row->age_seconds),
                'date'       => $row->day,
            ])
            ->all();
    }

    private function activityLabel(object $row): string
    {
        if ($row->kind === 'document') {
            return 'Uploaded document';
        }
        $verb = (int) $row->edited_after > 1
            ? 'Edited'
            : ($row->status === 'published' ? 'Published' : 'Added');

        return "{$verb} {$row->kind}";
    }
}
