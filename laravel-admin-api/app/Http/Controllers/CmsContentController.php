<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CmsContentController extends Controller
{
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function health(): JsonResponse
    {
        DB::select('SELECT 1');

        return response()->json(['status' => 'ok', 'database' => DB::connection()->getDatabaseName()]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'newsCategories'         => DB::table('news_categories')->orderBy('name')->get(['category_id as id', 'name']),
            'announcementCategories' => DB::table('announcement_categories')->orderBy('name')->get(['category_id as id', 'name']),
            'eventCategories'        => $this->eventCategories(),
            'campuses'               => DB::table('campuses')->orderBy('name')->get(['campus_id as id', 'name', 'status']),
            'colleges'               => DB::table('colleges')->orderBy('name')->get(['college_id as id', 'name', 'code', 'status']),
            'departments'            => DB::table('departments')->orderBy('name')->get(['department_id as id', 'college_id as collegeId', 'name']),
            'degreeLevels'           => DB::table('programs')->select('degree_level')->distinct()->orderBy('degree_level')->pluck('degree_level'),
            'userRoles'              => DB::table('users')->select('role')->distinct()->orderBy('role')->pluck('role'),
        ]);
    }

    public function newsIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q'        => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'integer', 'exists:news_categories,category_id'],
            'status'   => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'campus'   => ['nullable', 'integer', 'exists:campuses,campus_id'],
            'date'     => ['nullable', Rule::in(['7', '30', '90', 'year'])],
        ]);

        $query = DB::table('news as n')
            ->leftJoin('campuses as c', 'c.campus_id', '=', 'n.campus_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'n.created_by')
            ->leftJoin('media as m', 'm.media_id', '=', 'n.cover_media_id')
            ->select([
                'n.news_id as id', 'n.title', 'n.author_name', 'u.full_name as creator_name',
                'n.excerpt as summary', 'n.content', 'n.status', 'n.published_at', 'n.campus_id',
                'c.name as campus_name', 'n.cover_media_id',
                DB::raw('(SELECT nc.category_id FROM news_category_map ncm JOIN news_categories nc ON nc.category_id = ncm.category_id WHERE ncm.news_id = n.news_id ORDER BY nc.category_id LIMIT 1) as category_id'),
                DB::raw('(SELECT nc.name FROM news_category_map ncm JOIN news_categories nc ON nc.category_id = ncm.category_id WHERE ncm.news_id = n.news_id ORDER BY nc.category_id LIMIT 1) as category_name'),
            ]);

        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('n.title', 'like', $term)
                    ->orWhere('n.author_name', 'like', $term)
                    ->orWhere('n.excerpt', 'like', $term)
                    ->orWhere('n.content', 'like', $term)
                    ->orWhere('c.name', 'like', $term)
                    ->orWhereExists(function ($category) use ($term): void {
                        $category->selectRaw('1')->from('news_category_map as ncm')
                            ->join('news_categories as nc', 'nc.category_id', '=', 'ncm.category_id')
                            ->whereColumn('ncm.news_id', 'n.news_id')->where('nc.name', 'like', $term);
                    });
            });
        }

        if (! empty($filters['category'])) {
            $query->whereExists(function ($category) use ($filters): void {
                $category->selectRaw('1')->from('news_category_map')
                    ->whereColumn('news_category_map.news_id', 'n.news_id')
                    ->where('news_category_map.category_id', $filters['category']);
            });
        }
        if (! empty($filters['status'])) {
            $query->where('n.status', $filters['status']);
        }
        if (! empty($filters['campus'])) {
            $query->where(function ($q) use ($filters): void {
                $q->whereNull('n.campus_id')->orWhere('n.campus_id', $filters['campus']);
            });
        }
        $this->applyDatePreset($query, 'n.published_at', $filters['date'] ?? null);

        $rows = $query->orderByDesc('n.published_at')->orderByDesc('n.news_id')->limit(100)->get();

        return response()->json($rows->map(fn(object $row): array=> $this->newsJson($row)));
    }

    public function newsShow(int $id): JsonResponse
    {
        return response()->json($this->newsJson($this->findNews($id)));
    }

    public function newsStore(Request $request): JsonResponse
    {
        return $this->saveNews($request);
    }

    public function newsUpdate(Request $request, int $id): JsonResponse
    {
        $this->findNews($id);

        return $this->saveNews($request, $id);
    }

    public function newsDestroy(int $id): JsonResponse
    {
        $this->findNews($id);
        DB::table('news')->where('news_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function announcementsIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q'        => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'integer', 'exists:announcement_categories,category_id'],
            'status'   => ['nullable', Rule::in(['draft', 'published', 'archived', 'expired'])],
            'campus'   => ['nullable', 'integer', 'exists:campuses,campus_id'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'date'     => ['nullable', Rule::in(['7', '30', '90', 'year'])],
        ]);

        $query = DB::table('announcements as a')
            ->leftJoin('campuses as c', 'c.campus_id', '=', 'a.campus_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'a.created_by')
            ->leftJoin('documents as d', 'd.document_id', '=', 'a.attachment_document_id')
            ->leftJoin('announcement_categories as ac', 'ac.category_id', '=', 'a.category_id')
            ->select([
                'a.announcement_id as id', 'a.title', 'a.content', 'a.priority', 'a.expires_at',
                'a.status', 'a.published_at', 'a.campus_id', 'c.name as campus_name',
                'u.full_name as author_name', 'd.document_id', 'd.file_name', 'd.file_path',
                'd.mime_type', 'd.file_size',
                'a.category_id', 'ac.name as category_name',
            ]);

        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('a.title', 'like', $term)
                    ->orWhere('a.content', 'like', $term)
                    ->orWhere('u.full_name', 'like', $term)
                    ->orWhere('c.name', 'like', $term)
                    ->orWhere('ac.name', 'like', $term);
            });
        }
        if (! empty($filters['category'])) {
            $query->where('a.category_id', $filters['category']);
        }
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'expired') {
                $query->where('a.status', 'published')->whereNotNull('a.expires_at')->where('a.expires_at', '<', now());
            } else {
                $query->where('a.status', $filters['status']);
            }
        }
        if (! empty($filters['campus'])) {
            $query->where(function ($q) use ($filters): void {
                $q->whereNull('a.campus_id')->orWhere('a.campus_id', $filters['campus']);
            });
        }
        if (! empty($filters['priority'])) {
            $query->where('a.priority', $filters['priority']);
        }
        $this->applyDatePreset($query, 'a.published_at', $filters['date'] ?? null);

        $rows = $query->orderByDesc('a.published_at')->orderByDesc('a.announcement_id')->limit(100)->get();

        return response()->json($rows->map(fn(object $row): array=> $this->announcementJson($row)));
    }

    public function announcementShow(int $id): JsonResponse
    {
        return response()->json($this->announcementJson($this->findAnnouncement($id)));
    }

    public function announcementStore(Request $request): JsonResponse
    {
        return $this->saveAnnouncement($request);
    }

    public function announcementUpdate(Request $request, int $id): JsonResponse
    {
        $this->findAnnouncement($id);

        return $this->saveAnnouncement($request, $id);
    }

    public function announcementDestroy(int $id): JsonResponse
    {
        $this->findAnnouncement($id);
        DB::table('announcements')->where('announcement_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function eventsIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q'        => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'integer', 'exists:event_categories,category_id'],
            'status'   => ['nullable', Rule::in(['draft', 'upcoming', 'ongoing', 'completed', 'cancelled', 'archived'])],
            'date'     => ['nullable', Rule::in(['7', '30', '90', 'year', 'past'])],
        ]);

        $query = DB::table('events as e')
            ->join('event_categories as ec', 'ec.category_id', '=', 'e.category_id')
            ->leftJoin('media as m', 'm.media_id', '=', 'e.cover_media_id')
            ->select([
                'e.event_id as id', 'e.title', 'e.description', 'e.category_id as categoryId',
                'ec.name as categoryName', 'e.venue', 'e.start_at', 'e.end_at', 'e.organizer',
                'e.status', 'e.cover_media_id',
            ]);

        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('e.title', 'like', $term)->orWhere('e.description', 'like', $term)
                    ->orWhere('e.venue', 'like', $term)->orWhere('e.organizer', 'like', $term)
                    ->orWhere('ec.name', 'like', $term);
            });
        }
        if (! empty($filters['category'])) {
            $query->where('e.category_id', $filters['category']);
        }
        if (! empty($filters['date'])) {
            $today = today()->toDateString();
            if ($filters['date'] === 'past') {
                $query->where('e.start_at', '<', now());
            } elseif ($filters['date'] === 'year') {
                $query->whereYear('e.start_at', (int) now()->year);
            } else {
                $query->whereDate('e.start_at', '>=', $today)
                    ->whereDate('e.start_at', '<=', today()->addDays((int) $filters['date'] - 1)->toDateString());
            }
        }

        $rows   = $query->orderBy('e.start_at')->orderBy('e.event_id')->limit(100)->get();
        $mapped = $rows->map(fn(object $row): array=> $this->eventJson($row));
        if (! empty($filters['status'])) {
            $mapped = $mapped->filter(fn(array $event): bool => $this->eventStatus($event) === $filters['status'])->values();
        }

        return response()->json($mapped);
    }

    public function eventShow(int $id): JsonResponse
    {
        return response()->json($this->eventJson($this->findEvent($id)));
    }

    public function eventStore(Request $request): JsonResponse
    {
        return $this->saveEvent($request);
    }

    public function eventUpdate(Request $request, int $id): JsonResponse
    {
        $this->findEvent($id);

        return $this->saveEvent($request, $id);
    }

    public function eventDestroy(int $id): JsonResponse
    {
        $this->findEvent($id);
        DB::table('events')->where('event_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function eventCategoriesIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
        ]);
        $query = DB::table('event_categories');

        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        return response()->json($query->orderBy('name')->get(['category_id as id', 'name', 'description']));
    }

    public function eventCategoryStore(Request $request): JsonResponse
    {
        $this->trimUniqueFields($request, ['name']);
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:event_categories,name'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $id = DB::table('event_categories')->insertGetId([
            'name'        => trim($data['name']),
            'description' => $data['description'] ?? null,
        ], 'category_id');
        DB::table('event_categories')->where('category_id', $id)->update(['id' => $id]);

        return response()->json($this->eventCategoryJson($id), 201);
    }

    public function eventCategoryUpdate(Request $request, int $id): JsonResponse
    {
        abort_unless(DB::table('event_categories')->where('category_id', $id)->exists(), 404);
        $this->trimUniqueFields($request, ['name']);
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', Rule::unique('event_categories', 'name')->ignore($id, 'category_id')],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        DB::table('event_categories')->where('category_id', $id)->update([
            'name'        => trim($data['name']),
            'description' => $data['description'] ?? null,
        ]);

        return response()->json($this->eventCategoryJson($id));
    }

    public function eventCategoryDestroy(int $id): JsonResponse
    {
        abort_unless(DB::table('event_categories')->where('category_id', $id)->exists(), 404);
        if (DB::table('events')->where('category_id', $id)->exists()) {
            return response()->json(['message' => 'Move or delete events that use this category first.'], 409);
        }
        DB::table('event_categories')->where('category_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    private function eventCategories()
    {
        return DB::table('event_categories')->orderBy('name')->get(['category_id as id', 'name', 'description']);
    }

    public function newsCategoriesIndex(Request $request): JsonResponse
    {
        return response()->json($this->contentCategories('news', $request));
    }

    public function newsCategoryStore(Request $request): JsonResponse
    {
        return $this->saveContentCategory($request, 'news');
    }

    public function newsCategoryUpdate(Request $request, int $id): JsonResponse
    {
        return $this->saveContentCategory($request, 'news', $id);
    }

    public function newsCategoryDestroy(int $id): JsonResponse
    {
        return $this->deleteContentCategory('news', $id);
    }

    public function announcementCategoriesIndex(Request $request): JsonResponse
    {
        return response()->json($this->contentCategories('announcement', $request));
    }

    public function announcementCategoryStore(Request $request): JsonResponse
    {
        return $this->saveContentCategory($request, 'announcement');
    }

    public function announcementCategoryUpdate(Request $request, int $id): JsonResponse
    {
        return $this->saveContentCategory($request, 'announcement', $id);
    }

    public function announcementCategoryDestroy(int $id): JsonResponse
    {
        return $this->deleteContentCategory('announcement', $id);
    }

    public function campusesIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q'      => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $query = DB::table('campuses')->select([
            'campus_id as id', 'name', 'code', 'address', 'status',
            DB::raw('(SELECT COUNT(*) FROM news WHERE news.campus_id = campuses.campus_id) as newsCount'),
            DB::raw('(SELECT COUNT(*) FROM announcements WHERE announcements.campus_id = campuses.campus_id) as announcementCount'),
        ]);
        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('address', 'like', $term);
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return response()->json($query->orderBy('name')->orderBy('campus_id')->limit(100)->get());
    }

    public function campusStore(Request $request): JsonResponse
    {
        return $this->saveCampus($request);
    }

    public function campusUpdate(Request $request, int $id): JsonResponse
    {
        abort_unless(DB::table('campuses')->where('campus_id', $id)->exists(), 404);

        return $this->saveCampus($request, $id);
    }

    public function campusDestroy(int $id): JsonResponse
    {
        abort_unless(DB::table('campuses')->where('campus_id', $id)->exists(), 404);
        $newsCount = DB::table('news')->where('campus_id', $id)->count();
        $announcementCount = DB::table('announcements')->where('campus_id', $id)->count();
        if ($newsCount || $announcementCount) {
            return response()->json([
                'message' => 'Move or clear the campus assignment on related news and announcements first.',
                'newsCount' => $newsCount,
                'announcementCount' => $announcementCount,
            ], 409);
        }
        DB::table('campuses')->where('campus_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function collegesIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q'      => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $query = DB::table('colleges as c')
            ->select([
                'c.college_id as id', 'c.name', 'c.slug', 'c.code', 'c.description',
                'c.dean_name as deanName', 'c.email as contactEmail', 'c.logo_media_id',
                'c.status',
                DB::raw('(SELECT COUNT(*) FROM departments d WHERE d.college_id = c.college_id) as departmentCount'),
                DB::raw('(SELECT COUNT(*) FROM programs p WHERE p.college_id = c.college_id) as programCount'),
            ]);
        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('c.name', 'like', $term)
                    ->orWhere('c.code', 'like', $term)
                    ->orWhere('c.dean_name', 'like', $term)
                    ->orWhere('c.email', 'like', $term)
                    ->orWhere('c.description', 'like', $term);
            });
        }
        if (! empty($filters['status'])) {
            $query->where('c.status', $filters['status']);
        }

        return response()->json($query->orderBy('c.name')->orderBy('c.college_id')->limit(100)->get()->map(
            fn (object $row): array => $this->collegeJson($row)
        ));
    }

    public function collegeShow(int $id): JsonResponse
    {
        $college = $this->findCollege($id);
        $record = $this->collegeJson($college);
        $record['departments'] = DB::table('departments')->where('college_id', $id)->orderBy('name')->get(['name', 'code']);
        $record['programs'] = DB::table('programs')->where('college_id', $id)->orderBy('name')->get(['name', 'code']);

        return response()->json($record);
    }

    public function collegeStore(Request $request): JsonResponse
    {
        return $this->saveCollege($request);
    }

    public function collegeUpdate(Request $request, int $id): JsonResponse
    {
        $this->findCollege($id);

        return $this->saveCollege($request, $id);
    }

    public function collegeDestroy(int $id): JsonResponse
    {
        $this->findCollege($id);
        $departments = DB::table('departments')->where('college_id', $id)->count();
        $programs = DB::table('programs')->where('college_id', $id)->count();
        if ($departments || $programs) {
            return response()->json([
                'message' => 'Move or delete this college’s departments and programs first.',
                'departments' => $departments,
                'programs' => $programs,
            ], 409);
        }
        DB::table('colleges')->where('college_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function programsIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q'        => ['nullable', 'string', 'max:200'],
            'college'  => ['nullable', 'integer', 'exists:colleges,college_id'],
            'level'    => ['nullable', 'string', 'max:50'],
            'status'   => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $query = DB::table('programs as p')
            ->join('colleges as c', 'c.college_id', '=', 'p.college_id')
            ->join('departments as d', 'd.department_id', '=', 'p.department_id')
            ->select([
                'p.program_id as id', 'p.name', 'p.slug', 'p.code', 'p.degree_level as degreeLevel',
                'p.college_id as collegeId', 'c.name as collegeName', 'c.code as collegeCode',
                'p.department_id as departmentId', 'd.name as departmentName',
                'p.description', 'p.status',
            ]);
        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('p.name', 'like', $term)
                    ->orWhere('p.code', 'like', $term)
                    ->orWhere('p.degree_level', 'like', $term)
                    ->orWhere('c.name', 'like', $term)
                    ->orWhere('c.code', 'like', $term)
                    ->orWhere('d.name', 'like', $term)
                    ->orWhere('p.description', 'like', $term);
            });
        }
        if (! empty($filters['college'])) {
            $query->where('p.college_id', $filters['college']);
        }
        if (! empty($filters['level'])) {
            $query->where('p.degree_level', $filters['level']);
        }
        if (! empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }

        return response()->json($query->orderBy('p.name')->orderBy('p.program_id')->limit(100)->get());
    }

    public function programShow(int $id): JsonResponse
    {
        return response()->json($this->findProgram($id));
    }

    public function programStore(Request $request): JsonResponse
    {
        return $this->saveProgram($request);
    }

    public function programUpdate(Request $request, int $id): JsonResponse
    {
        $this->findProgram($id);

        return $this->saveProgram($request, $id);
    }

    public function programDestroy(int $id): JsonResponse
    {
        $this->findProgram($id);
        DB::table('programs')->where('program_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function usersIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q'      => ['nullable', 'string', 'max:200'],
            'role'   => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $roleExpression = $this->normalizedUserRoleExpression();
        $query = DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->select([
                'u.user_id as id', 'u.full_name as fullName', 'u.email',
                DB::raw($roleExpression . ' as role'), 'u.status',
            ]);
        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('u.full_name', 'like', $term)
                    ->orWhere('u.email', 'like', $term)
                    ->orWhereRaw($this->normalizedUserRoleExpression() . ' LIKE ?', [$term]);
            });
        }
        if (! empty($filters['role'])) {
            $query->whereRaw($roleExpression . ' = ?', [$filters['role']]);
        }
        if (! empty($filters['status'])) {
            $query->where('u.status', $filters['status']);
        }

        return response()->json($query->orderBy('u.full_name')->orderBy('u.user_id')->limit(100)->get());
    }

    public function userShow(int $id): JsonResponse
    {
        return response()->json($this->findUser($id));
    }

    public function userStore(Request $request): JsonResponse
    {
        return $this->saveUser($request);
    }

    public function userUpdate(Request $request, int $id): JsonResponse
    {
        $this->findUser($id);

        return $this->saveUser($request, $id);
    }

    public function userDestroy(int $id): JsonResponse
    {
        $this->findUser($id);
        if (request()->user()?->user_id === $id) {
            return response()->json(['message' => 'You cannot delete the currently signed-in account.'], 422);
        }
        $activeAdmins = DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->where('u.status', 'active')
            ->whereRaw($this->normalizedUserRoleExpression() . ' = ?', ['admin'])
            ->count();
        $target = DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->where('u.user_id', $id)
            ->selectRaw($this->normalizedUserRoleExpression() . ' as role, u.status')
            ->first();
        if ($target && $target->role === 'admin' && $target->status === 'active' && $activeAdmins <= 1) {
            return response()->json(['message' => 'The final active administrator cannot be deleted.'], 422);
        }
        DB::table('users')->where('user_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function userRolesIndex(): JsonResponse
    {
        $roleExpression = $this->normalizedUserRoleExpression();
        $roles = DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->selectRaw($roleExpression . ' as name, COUNT(*) as userCount')
            ->groupByRaw($roleExpression)
            ->orderBy('name')
            ->get();

        return response()->json($roles);
    }

    public function userRoleUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currentName' => ['required', 'string', 'max:50'],
            'name'        => ['required', 'string', 'max:50', 'regex:/\S/'],
        ]);
        $oldName = trim($data['currentName']);
        $newName = trim($data['name']);
        $roleExpression = $this->normalizedUserRoleExpression();
        $userIds = DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->whereRaw($roleExpression . ' = ?', [$oldName])
            ->pluck('u.user_id');
        $existing = $userIds->count();
        abort_unless($existing > 0, 404, 'The role is no longer assigned to any users.');
        $roleExists = DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->whereRaw($roleExpression . ' = ?', [$newName])
            ->exists();
        if (strcasecmp($oldName, $newName) !== 0 && $roleExists) {
            return response()->json(['message' => 'A role with this name already exists.'], 422);
        }
        DB::transaction(function () use ($userIds, $newName): void {
            DB::table('users')->whereIn('user_id', $userIds)->update(['role' => $newName, 'updated_at' => now()]);
        });

        return response()->json(['name' => $newName, 'userCount' => $existing]);
    }

    private function contentCategories(string $kind, Request $request): \Illuminate\Support\Collection
    {
        $config = $this->contentCategoryConfig($kind);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $query = DB::table($config['table'] . ' as c')->select([
            'c.' . $config['id'] . ' as id', 'c.name', 'c.slug', 'c.description',
            DB::raw('(SELECT COUNT(*) FROM ' . $config['map'] . ' m WHERE m.' . $config['mapCategory'] . ' = c.' . $config['id'] . ') as contentCount'),
        ]);
        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('c.name', 'like', $term)->orWhere('c.description', 'like', $term);
            });
        }

        return $query->orderBy('c.name')->orderBy('c.' . $config['id'])->limit(100)->get();
    }

    private function saveContentCategory(Request $request, string $kind, ?int $id = null): JsonResponse
    {
        $config = $this->contentCategoryConfig($kind);
        $existing = $id ? DB::table($config['table'])->where($config['id'], $id)->first() : null;
        abort_unless($id === null || $existing !== null, 404);
        $this->trimUniqueFields($request, ['name']);
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'regex:/\S/', Rule::unique($config['table'], 'name')->ignore($id, $config['id'])],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $name = trim($data['name']);
        $values = [
            'name' => $name,
            'description' => trim($data['description'] ?? '') ?: null,
            'updated_at' => now(),
        ];
        if ($existing === null) {
            $values['slug'] = $this->uniqueSlug($config['table'], 'slug', $name);
            $values['created_at'] = now();
            $categoryId = DB::table($config['table'])->insertGetId($values, $config['id']);
        } else {
            if ($existing->name !== $name) {
                $values['slug'] = $this->uniqueSlugExcept($config['table'], 'slug', $name, $config['id'], $id);
            }
            DB::table($config['table'])->where($config['id'], $id)->update($values);
            $categoryId = $id;
        }

        return response()->json($this->contentCategoryJson($kind, $categoryId), $id === null ? 201 : 200);
    }

    private function deleteContentCategory(string $kind, int $id): JsonResponse
    {
        $config = $this->contentCategoryConfig($kind);
        abort_unless(DB::table($config['table'])->where($config['id'], $id)->exists(), 404);
        $count = DB::table($config['map'])->where($config['mapCategory'], $id)->count();
        if ($count) {
            return response()->json([
                'message' => 'Move the linked content to another category before deleting this category.',
                'contentCount' => $count,
            ], 409);
        }
        DB::table($config['table'])->where($config['id'], $id)->delete();

        return response()->json(['deleted' => true]);
    }

    private function contentCategoryConfig(string $kind): array
    {
        $configs = [
            'news' => [
                'table' => 'news_categories', 'id' => 'category_id',
                'map' => 'news_category_map', 'mapCategory' => 'category_id',
            ],
            'announcement' => [
                'table' => 'announcement_categories', 'id' => 'category_id',
                'map' => 'announcements', 'mapCategory' => 'category_id',
            ],
        ];
        abort_unless(isset($configs[$kind]), 404);

        return $configs[$kind];
    }

    private function contentCategoryJson(string $kind, int $id): object
    {
        $config = $this->contentCategoryConfig($kind);
        $row = DB::table($config['table'] . ' as c')
            ->select([
                'c.' . $config['id'] . ' as id', 'c.name', 'c.slug', 'c.description',
                DB::raw('(SELECT COUNT(*) FROM ' . $config['map'] . ' m WHERE m.' . $config['mapCategory'] . ' = c.' . $config['id'] . ') as contentCount'),
            ])
            ->where('c.' . $config['id'], $id)
            ->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    private function saveCampus(Request $request, ?int $id = null): JsonResponse
    {
        $this->trimUniqueFields($request, ['name', 'code'], ['code']);
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:150', 'regex:/\S/', Rule::unique('campuses', 'name')->ignore($id, 'campus_id')],
            'code'    => ['nullable', 'string', 'max:30', Rule::unique('campuses', 'code')->ignore($id, 'campus_id')],
            'address' => ['nullable', 'string', 'max:255'],
            'status'  => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $code = trim($data['code'] ?? '');
        if ($code === '') {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', trim($data['name'])), 0, 8)) ?: 'CAMPUS';
        }
        $values = [
            'name' => trim($data['name']),
            'code' => $code,
            'address' => trim($data['address'] ?? '') ?: null,
            'status' => $data['status'],
            'updated_at' => now(),
        ];
        if ($id === null) {
            $values['created_at'] = now();
            $id = DB::table('campuses')->insertGetId($values, 'campus_id');
        } else {
            DB::table('campuses')->where('campus_id', $id)->update($values);
        }
        $campus = DB::table('campuses')->select([
            'campus_id as id', 'name', 'code', 'address', 'status',
            DB::raw('(SELECT COUNT(*) FROM news WHERE news.campus_id = campuses.campus_id) as newsCount'),
            DB::raw('(SELECT COUNT(*) FROM announcements WHERE announcements.campus_id = campuses.campus_id) as announcementCount'),
        ])->where('campus_id', $id)->first();

        return response()->json($campus, $request->isMethod('post') ? 201 : 200);
    }

    public function departmentsIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q'        => ['nullable', 'string', 'max:200'],
            'college'  => ['nullable', 'integer', 'exists:colleges,college_id'],
            'status'   => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $query = DB::table('departments as d')
            ->join('colleges as c', 'c.college_id', '=', 'd.college_id')
            ->leftJoin('faculty_staff as f', 'f.faculty_id', '=', 'd.head_faculty_id')
            ->select([
                'd.department_id as id', 'd.name', 'd.slug', 'd.code',
                'd.college_id as collegeId', 'c.name as collegeName', 'c.code as collegeCode',
                'd.head_faculty_id as headId',
                DB::raw("TRIM(CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.middle_name, ''), ' ', COALESCE(f.last_name, ''))) as headName"),
                'd.description', 'd.status',
                DB::raw('(SELECT COUNT(*) FROM programs p WHERE p.department_id = d.department_id) as programCount'),
            ]);
        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('d.name', 'like', $term)
                    ->orWhere('d.code', 'like', $term)
                    ->orWhereRaw("CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.middle_name, ''), ' ', COALESCE(f.last_name, '')) like ?", [$term])
                    ->orWhere('c.name', 'like', $term)
                    ->orWhere('c.code', 'like', $term)
                    ->orWhere('d.description', 'like', $term);
            });
        }
        if (! empty($filters['college'])) {
            $query->where('d.college_id', $filters['college']);
        }
        if (! empty($filters['status'])) {
            $query->where('d.status', $filters['status']);
        }

        return response()->json($query->orderBy('c.name')->orderBy('d.name')->orderBy('d.department_id')->limit(100)->get());
    }

    public function departmentShow(int $id): JsonResponse
    {
        $department = $this->findDepartment($id);
        $department->programs = DB::table('programs')
            ->where('department_id', $id)
            ->orderBy('name')
            ->get(['name', 'code']);
        $department->staff = DB::table('faculty_staff')
            ->where(function ($query) use ($id, $department): void {
                $query->where('department_id', $id);
                if ($department->headId) {
                    $query->orWhere('faculty_id', $department->headId);
                }
            })
            ->orderBy('last_name')
            ->get([DB::raw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(middle_name, ''), ' ', COALESCE(last_name, ''))) as name"), 'position']);

        return response()->json($department);
    }

    public function departmentStore(Request $request): JsonResponse
    {
        return $this->saveDepartment($request);
    }

    public function departmentUpdate(Request $request, int $id): JsonResponse
    {
        $this->findDepartment($id);

        return $this->saveDepartment($request, $id);
    }

    public function departmentDestroy(int $id): JsonResponse
    {
        $this->findDepartment($id);
        if (DB::table('programs')->where('department_id', $id)->exists()) {
            return response()->json(['message' => 'Move or delete academic programs in this department first.'], 409);
        }
        DB::table('departments')->where('department_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function facultyIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q'          => ['nullable', 'string', 'max:200'],
            'department' => ['nullable', 'integer', 'exists:departments,department_id'],
        ]);
        $query = DB::table('faculty_staff as f')
            ->leftJoin('departments as d', 'd.department_id', '=', 'f.department_id')
            ->select([
                'f.faculty_id as id',
                DB::raw("TRIM(CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.middle_name, ''), ' ', COALESCE(f.last_name, ''))) as name"),
                'f.position', 'f.department_id as departmentId',
                'd.name as department', 'f.email', 'f.biography as summary',
            ]);

        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw("CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.middle_name, ''), ' ', COALESCE(f.last_name, '')) like ?", [$term])
                    ->orWhere('f.position', 'like', $term)
                    ->orWhere('f.email', 'like', $term)
                    ->orWhere('f.biography', 'like', $term)
                    ->orWhere('d.name', 'like', $term);
            });
        }
        if (! empty($filters['department'])) {
            $query->where('f.department_id', $filters['department']);
        }

        return response()->json($query->orderBy('f.last_name')->orderBy('f.faculty_id')->limit(100)->get());
    }

    public function facultyShow(int $id): JsonResponse
    {
        return response()->json($this->findFaculty($id));
    }

    public function facultyStore(Request $request): JsonResponse
    {
        return $this->saveFaculty($request);
    }

    public function facultyUpdate(Request $request, int $id): JsonResponse
    {
        $this->findFaculty($id);

        return $this->saveFaculty($request, $id);
    }

    public function facultyDestroy(int $id): JsonResponse
    {
        $this->findFaculty($id);
        if (DB::table('departments')->where('head_faculty_id', $id)->exists()) {
            return response()->json(['message' => 'Remove this person as a department head before deleting the record.'], 409);
        }
        DB::table('faculty_staff')->where('faculty_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function mediaIndex(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $query = DB::table('media')->select([
            'media_id as id', 'file_name as fileName', 'mime_type as mimeType',
            'file_size as fileSize', 'alt_text as altText', 'file_path as filePath', 'created_at as createdAt',
        ]);
        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('file_name', 'like', $term)->orWhere('alt_text', 'like', $term);
            });
        }
        $rows = $query->orderByDesc('media_id')->limit(100)->get();

        return response()->json($rows->map(fn (object $row): array => [
            'id'        => (int) $row->id,
            'fileName'  => $row->fileName,
            'mimeType'  => $row->mimeType,
            'fileSize'  => $row->fileSize === null ? null : (int) $row->fileSize,
            'altText'   => $row->altText ?? '',
            'createdAt' => $row->createdAt,
            'available' => $this->storedFileExists((string) $row->filePath),
            'url'       => $this->mediaUrl((int) $row->id),
        ]));
    }

    public function mediaStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'data'    => ['required', 'string', 'max:7500000'],
            'altText' => ['nullable', 'string', 'max:255'],
        ]);
        $id = $this->transactionWithUploadCleanup(function (array &$createdFiles) use ($data): int {
            $id = $this->storeImage($data['data'], $createdFiles);
            abort_unless($id !== null, 422, 'Select an image file to upload.');
            DB::table('media')->where('media_id', $id)->update(['alt_text' => trim($data['altText'] ?? '') ?: null]);

            return $id;
        });

        return response()->json($this->mediaJson($id), 201);
    }

    public function mediaUpdate(Request $request, int $id): JsonResponse
    {
        abort_unless(DB::table('media')->where('media_id', $id)->exists(), 404);
        $data = $request->validate(['altText' => ['nullable', 'string', 'max:255']]);
        DB::table('media')->where('media_id', $id)->update(['alt_text' => trim($data['altText'] ?? '') ?: null]);

        return response()->json($this->mediaJson($id));
    }

    public function mediaDestroy(int $id): JsonResponse
    {
        $media = DB::table('media')->where('media_id', $id)->first();
        abort_unless($media !== null, 404);
        $references = [
            'news' => DB::table('news')->where('cover_media_id', $id)->count(),
            'events' => DB::table('events')->where('cover_media_id', $id)->count(),
            'colleges' => DB::table('colleges')->where('logo_media_id', $id)->count(),
        ];
        if (array_sum($references) > 0) {
            return response()->json([
                'message' => 'This media file is in use. Remove its references before deleting it.',
                'references' => $references,
            ], 409);
        }
        $this->deleteStoredUpload((string) $media->file_path);
        DB::table('media')->where('media_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function documentsIndex(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $query = DB::table('documents')->select([
            'document_id as id', 'title', 'file_name as fileName', 'mime_type as mimeType',
            'file_size as fileSize', 'file_path as filePath', 'created_at as createdAt',
        ]);
        if (! empty($filters['q'])) {
            $term = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('title', 'like', $term)->orWhere('file_name', 'like', $term);
            });
        }
        $rows = $query->orderByDesc('document_id')->limit(100)->get();

        return response()->json($rows->map(fn (object $row): array => [
            'id'        => (int) $row->id,
            'title'     => $row->title ?? '',
            'fileName'  => $row->fileName,
            'mimeType'  => $row->mimeType,
            'fileSize'  => $row->fileSize === null ? null : (int) $row->fileSize,
            'createdAt' => $row->createdAt,
            'available' => $this->storedFileExists((string) $row->filePath),
            'url'       => route('cms.document', ['id' => (int) $row->id]),
        ]));
    }

    public function documentStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'    => ['nullable', 'string', 'max:255'],
            'fileName' => ['required', 'string', 'max:255'],
            'data'     => ['required', 'string', 'max:600000'],
        ]);
        $id = $this->transactionWithUploadCleanup(function (array &$createdFiles) use ($data): int {
            $id = $this->storeDocument(['name' => $data['fileName'], 'data' => $data['data']], $createdFiles);
            if (! empty($data['title'])) {
                DB::table('documents')->where('document_id', $id)->update(['title' => trim($data['title'])]);
            }

            return $id;
        });

        return response()->json($this->documentJson($id), 201);
    }

    public function documentUpdate(Request $request, int $id): JsonResponse
    {
        abort_unless(DB::table('documents')->where('document_id', $id)->exists(), 404);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:255']]);
        DB::table('documents')->where('document_id', $id)->update(['title' => trim($data['title'] ?? '') ?: null]);

        return response()->json($this->documentJson($id));
    }

    public function documentDestroy(int $id): JsonResponse
    {
        $document = DB::table('documents')->where('document_id', $id)->first();
        abort_unless($document !== null, 404);
        $references = DB::table('announcements')->where('attachment_document_id', $id)->count();
        if ($references) {
            return response()->json([
                'message' => 'This document is attached to one or more announcements. Remove those references before deleting it.',
                'references' => $references,
            ], 409);
        }
        $this->deleteStoredUpload((string) $document->file_path);
        DB::table('documents')->where('document_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function mediaShow(int $id): Response
    {
        $media = DB::table('media')->where('media_id', $id)->first();
        abort_unless($media !== null, 404);
        $inline = in_array((string) $media->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);

        return $this->storedFileResponse((string) $media->file_path, $media->mime_type, $media->file_name, ! $inline);
    }

    public function documentShow(int $id): Response
    {
        $document = DB::table('documents')->where('document_id', $id)->first();
        abort_unless($document !== null, 404);

        return $this->storedFileResponse((string) $document->file_path, $document->mime_type, $document->file_name);
    }

    private function saveNews(Request $request, ?int $id = null): JsonResponse
    {
        $data = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'categoryId'       => ['required', 'integer', 'exists:news_categories,category_id'],
            'campusId'         => ['nullable', 'integer', 'exists:campuses,campus_id'],
            'author'           => ['nullable', 'string', 'max:150'],
            'publishedAt'      => ['nullable', 'date'],
            'shortDescription' => ['nullable', 'string', 'max:5000'],
            'content'          => ['nullable', 'string', 'max:2000000'],
            'status'           => ['required', Rule::in(['draft', 'published', 'archived'])],
            'image'            => ['nullable', 'string', 'max:7500000'],
        ]);

        $record = $this->transactionWithUploadCleanup(function (array &$createdFiles) use ($data, $id): int {
            $existing   = $id ? DB::table('news')->where('news_id', $id)->first() : null;
            $imageId    = $this->storeImage($data['image'] ?? null, $createdFiles);
            $imageValue = $data['image'] ?? null;
            $values     = [
                'title'          => trim($data['title']),
                'author_name'    => $data['author'] ?? null,
                'excerpt'        => $data['shortDescription'] ?? null,
                'content'        => $this->sanitizeRichText($data['content'] ?? ''),
                'status'         => $data['status'],
                'published_at'   => ($data['publishedAt'] ?? null) ?: ($data['status'] === 'published' ? now() : null),
                'campus_id'      => $data['campusId'] ?? null,
                'cover_media_id' => str_starts_with((string) $imageValue, 'data:')
                    ? $imageId
                    : ($imageValue === '' ? null : ($existing->cover_media_id ?? null)),
                'updated_at'     => now(),
            ];
            if (! $existing) {
                $values['created_by'] = request()->user()?->user_id;
                $values['slug']       = $this->uniqueSlug('news', 'slug', $data['title']);
                $values['created_at'] = now();
                $recordId             = DB::table('news')->insertGetId($values, 'news_id');
            } else {
                DB::table('news')->where('news_id', $id)->update($values);
                $recordId = $id;
            }

            DB::table('news_category_map')->where('news_id', $recordId)->delete();
            DB::table('news_category_map')->insert([
                'news_id'     => $recordId,
                'category_id' => $data['categoryId'],
            ]);

            return $recordId;
        });

        return response()->json($this->newsJson($this->findNews($record)), $id ? 200 : 201);
    }

    private function saveAnnouncement(Request $request, ?int $id = null): JsonResponse
    {
        $data = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'categoryId'      => ['required', 'integer', 'exists:announcement_categories,category_id'],
            'campusId'        => ['nullable', 'integer', 'exists:campuses,campus_id'],
            'priority'        => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'publishedAt'     => ['nullable', 'date'],
            'expiresAt'       => ['nullable', 'date', 'after_or_equal:publishedAt'],
            'content'         => ['nullable', 'string', 'max:2000000'],
            'status'          => ['required', Rule::in(['draft', 'published', 'archived'])],
            'attachment'      => ['nullable', 'array'],
            'attachment.name' => ['nullable', 'string', 'max:255'],
            'attachment.size' => ['nullable', 'integer', 'min:0', 'max:12288000'],
            'attachment.type' => ['nullable', 'string', 'max:100'],
            'attachment.data' => ['nullable', 'string', 'max:600000'],
            'attachment.id'   => ['nullable', 'integer', 'exists:documents,document_id'],
            'attachment.url'  => ['nullable', 'url', 'max:2000'],
        ]);
        $record = $this->transactionWithUploadCleanup(function (array &$createdFiles) use ($data, $id): int {
            $existing   = $id ? DB::table('announcements')->where('announcement_id', $id)->first() : null;
            $attachment = $data['attachment'] ?? null;
            $documentId = $attachment && ! empty($attachment['data'])
                ? $this->storeDocument($attachment, $createdFiles)
                : (($attachment && $existing && (int) ($attachment['id'] ?? 0) === (int) $existing->attachment_document_id)
                    ? $existing->attachment_document_id
                    : null);
            $values = [
                'title'                  => trim($data['title']),
                'author_name'            => request()->user()?->full_name,
                'category_id'            => $data['categoryId'],
                'content'                => $this->sanitizeRichText($data['content'] ?? ''),
                'priority'               => $data['priority'],
                'expires_at'             => isset($data['expiresAt'])
                    ? Carbon::parse($data['expiresAt'])->endOfDay()->format('Y-m-d H:i:s')
                    : null,
                'status'                 => $data['status'],
                'published_at'           => ($data['publishedAt'] ?? null) ?: ($data['status'] === 'published' ? now() : null),
                'campus_id'              => $data['campusId'] ?? null,
                'attachment_document_id' => $documentId,
                'updated_at'             => now(),
            ];
            if (! $existing) {
                $values['created_by'] = request()->user()?->user_id;
                $values['created_at'] = now();
                $recordId             = DB::table('announcements')->insertGetId($values, 'announcement_id');
            } else {
                DB::table('announcements')->where('announcement_id', $id)->update($values);
                $recordId = $id;
            }
            DB::table('announcements')->where('announcement_id', $recordId)->update(['category_id' => $data['categoryId']]);

            return $recordId;
        });

        return response()->json($this->announcementJson($this->findAnnouncement($record)), $id ? 200 : 201);
    }

    private function saveUser(Request $request, ?int $id = null): JsonResponse
    {
        $this->trimUniqueFields($request, ['email']);
        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:150', 'regex:/\S/'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id, 'user_id')],
            'role'     => ['required', Rule::in(['admin', 'editor'])],
            'status'   => ['required', Rule::in(['active', 'inactive'])],
            'password' => $id === null
                ? ['required', 'string', 'min:12', 'max:1024']
                : ['nullable', 'string', 'min:12', 'max:1024'],
        ]);
        $fullName = trim($data['fullName']);
        $nameParts = preg_split('/\s+/', $fullName, 2) ?: [$fullName];
        $roleName = $data['role'] === 'admin' ? 'Administrator' : 'Editor';
        $roleId = DB::table('roles')->where('name', $roleName)->value('role_id');
        abort_unless($roleId, 422, 'The selected role is unavailable.');
        $campusId = DB::table('campuses')->orderBy('campus_id')->value('campus_id');
        abort_unless($campusId, 422, 'A campus is required for users.');
        $values = [
            'full_name'  => $fullName,
            'first_name' => $nameParts[0],
            'last_name'  => $nameParts[1] ?? '',
            'campus_id'  => $campusId,
            'role_id'    => $roleId,
            'email'      => trim($data['email']),
            'role'       => trim($data['role']),
            'status'     => $data['status'],
            'updated_at' => now(),
        ];
        if ($id === null || ! empty($data['password'])) {
            $values['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if ($id === null) {
            $values['created_at'] = now();
            $values['username'] = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $fullName) ?: 'user' . time());
            $baseUsername = $values['username'];
            $suffix = 2;
            while (DB::table('users')->where('username', $values['username'])->exists()) {
                $values['username'] = $baseUsername . $suffix++;
            }
            $userId = DB::table('users')->insertGetId($values, 'user_id');
        } else {
            DB::table('users')->where('user_id', $id)->update($values);
            $userId = $id;
        }

        return response()->json($this->findUser($userId), $id === null ? 201 : 200);
    }

    private function saveCollege(Request $request, ?int $id = null): JsonResponse
    {
        $this->trimUniqueFields($request, ['name', 'code']);
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:200', 'regex:/\S/'],
            'code'        => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('colleges', 'code')->ignore($id, 'college_id')],
            'description' => ['nullable', 'string', 'max:20000'],
            'deanName'    => ['nullable', 'string', 'max:150'],
            'contactEmail'=> ['nullable', 'email', 'max:255'],
            'image'       => ['nullable', 'string', 'max:7500000'],
            'status'      => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $data['name'] = trim($data['name']);
        $data['code'] = strtoupper(trim($data['code']));

        $collegeId = $this->transactionWithUploadCleanup(function (array &$createdFiles) use ($data, $id): int {
            $existing = $id ? DB::table('colleges')->where('college_id', $id)->first() : null;
            $imageValue = $data['image'] ?? null;
            $imageId = is_string($imageValue) && str_starts_with($imageValue, 'data:')
                ? $this->storeImage($imageValue, $createdFiles)
                : ($imageValue === '' ? null : ($existing->logo_media_id ?? null));
            $values = [
                'name'          => $data['name'],
                'code'          => $data['code'],
                'campus_id'     => DB::table('campuses')->orderBy('campus_id')->value('campus_id'),
                'description'   => $this->sanitizeRichText($data['description'] ?? ''),
                'dean_name'     => isset($data['deanName']) ? trim($data['deanName']) : null,
                'email'         => isset($data['contactEmail']) ? trim($data['contactEmail']) : null,
                'logo_media_id' => $imageId,
                'status'        => $data['status'],
                'updated_at'    => now(),
            ];
            if ($id === null) {
                $values['created_by'] = request()->user()?->user_id;
                $values['slug'] = $this->uniqueSlug('colleges', 'slug', $data['name']);
                $values['created_at'] = now();

                return DB::table('colleges')->insertGetId($values, 'college_id');
            }

            DB::table('colleges')->where('college_id', $id)->update($values);

            return $id;
        });

        return response()->json($this->collegeJson($this->findCollege($collegeId)), $id === null ? 201 : 200);
    }

    private function saveProgram(Request $request, ?int $id = null): JsonResponse
    {
        $this->trimUniqueFields($request, ['name', 'code']);
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:200', 'regex:/\S/'],
            'code'         => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9 .-]+$/', Rule::unique('programs', 'code')->ignore($id, 'program_id')],
            'degreeLevel'  => ['required', 'string', 'max:50'],
            'collegeId'    => ['required', 'integer', 'exists:colleges,college_id'],
            'departmentId' => ['required', 'integer', 'exists:departments,department_id'],
            'description'  => ['nullable', 'string', 'max:20000'],
            'status'       => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $departmentBelongsToCollege = DB::table('departments')
            ->where('department_id', $data['departmentId'])
            ->where('college_id', $data['collegeId'])
            ->exists();
        abort_unless($departmentBelongsToCollege, 422, 'Choose a department in the selected college.');
        $data['name'] = trim($data['name']);
        $data['code'] = trim($data['code']);

        $programId = DB::transaction(function () use ($data, $id): int {
            $values = [
                'name'          => $data['name'],
                'code'          => $data['code'],
                'degree_level'  => trim($data['degreeLevel']),
                'college_id'    => $data['collegeId'],
                'department_id' => $data['departmentId'],
                'description'   => $this->sanitizeRichText($data['description'] ?? ''),
                'status'        => $data['status'],
                'updated_at'    => now(),
            ];
            if ($id === null) {
                $values['slug'] = $this->uniqueSlug('programs', 'slug', $data['name']);
                $values['created_at'] = now();

                return DB::table('programs')->insertGetId($values, 'program_id');
            }
            DB::table('programs')->where('program_id', $id)->update($values);

            return $id;
        });

        return response()->json($this->findProgram($programId), $id === null ? 201 : 200);
    }

    private function saveFaculty(Request $request, ?int $id = null): JsonResponse
    {
        $this->trimUniqueFields($request, ['email'], ['email']);
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'position' => trim((string) $request->input('position')),
            'summary' => trim((string) $request->input('summary')),
        ]);
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'position'     => ['nullable', 'string', 'max:150'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,department_id'],
            'email'        => ['nullable', 'email', 'max:255', Rule::unique('faculty_staff', 'email')->ignore($id, 'faculty_id')],
            'summary'      => ['nullable', 'string', 'max:5000'],
        ]);
        $nameParts = preg_split('/\s+/', trim($data['name']), 3) ?: [trim($data['name'])];
        $firstName = $nameParts[0] ?? '';
        $middleName = count($nameParts) === 3 ? ($nameParts[1] ?? null) : null;
        $lastName = count($nameParts) >= 2 ? end($nameParts) : '';
        $values = [
            'first_name'    => $firstName,
            'middle_name'   => $middleName,
            'last_name'     => $lastName,
            'position'      => isset($data['position']) ? trim($data['position']) : null,
            'department_id' => $data['departmentId'] ?? null,
            'email'         => isset($data['email']) ? trim($data['email']) : null,
            'biography'     => isset($data['summary']) ? trim($data['summary']) : null,
            'updated_at'    => now(),
        ];
        if ($id === null) {
            $values['created_at'] = now();
            $values['campus_id'] = DB::table('departments')->where('department_id', $data['departmentId'] ?? 0)->value('campus_id')
                ?? DB::table('campuses')->orderBy('campus_id')->value('campus_id');
            abort_unless($values['campus_id'], 422, 'A campus is required for faculty records.');
            $facultyId = DB::table('faculty_staff')->insertGetId($values, 'faculty_id');
        } else {
            DB::table('faculty_staff')->where('faculty_id', $id)->update($values);
            $facultyId = $id;
        }

        return response()->json($this->findFaculty($facultyId), $id === null ? 201 : 200);
    }

    private function saveDepartment(Request $request, ?int $id = null): JsonResponse
    {
        $this->trimUniqueFields($request, ['name', 'code']);
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:200', 'regex:/\S/'],
            'code'          => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('departments', 'code')->ignore($id, 'department_id')],
            'collegeId'     => ['required', 'integer', 'exists:colleges,college_id'],
            'headFacultyId' => ['nullable', 'integer', 'exists:faculty_staff,faculty_id'],
            'description'   => ['nullable', 'string', 'max:20000'],
            'status'        => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $data['name'] = trim($data['name']);
        $data['code'] = strtoupper(trim($data['code']));

        $departmentId = DB::transaction(function () use ($data, $id): int {
            $campusId = DB::table('colleges')->where('college_id', $data['collegeId'])->value('campus_id')
                ?? DB::table('campuses')->orderBy('campus_id')->value('campus_id');
            abort_unless($campusId, 422, 'A campus is required for departments.');
            $values = [
                'name'           => $data['name'],
                'code'           => $data['code'],
                'college_id'     => $data['collegeId'],
                'campus_id'      => $campusId,
                'head_faculty_id' => $data['headFacultyId'] ?? null,
                'description'    => $this->sanitizeRichText($data['description'] ?? ''),
                'status'         => $data['status'],
                'updated_at'     => now(),
            ];
            if ($id === null) {
                $values['created_by'] = request()->user()?->user_id;
                $values['slug']       = $this->uniqueSlug('departments', 'slug', $data['name']);
                $values['created_at'] = now();

                return DB::table('departments')->insertGetId($values, 'department_id');
            }

            $oldCollegeId = DB::table('departments')->where('department_id', $id)->value('college_id');
            DB::table('departments')->where('department_id', $id)->update($values);
            if ((int) $oldCollegeId !== (int) $data['collegeId']) {
                DB::table('programs')->where('department_id', $id)->update([
                    'college_id' => $data['collegeId'],
                    'updated_at' => now(),
                ]);
            }

            return $id;
        });

        return response()->json($this->findDepartment($departmentId), $id === null ? 201 : 200);
    }

    private function saveEvent(Request $request, ?int $id = null): JsonResponse
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'categoryId'  => ['required', 'integer', 'exists:event_categories,category_id'],
            'description' => ['nullable', 'string', 'max:2000000'],
            'venue'       => ['nullable', 'string', 'max:255'],
            'startDate'   => ['required', 'date'],
            'endDate'     => ['nullable', 'date', 'after_or_equal:startDate'],
            'startTime'   => ['nullable', 'date_format:H:i'],
            'endTime'     => ['nullable', 'date_format:H:i'],
            'organizer'   => ['nullable', 'string', 'max:255'],
            'state'       => ['required', Rule::in(['draft', 'published'])],
            'banner'      => ['nullable', 'string', 'max:7500000'],
        ]);
        $startAt     = Carbon::parse($data['startDate'] . ' ' . ($data['startTime'] ?? '00:00'))->format('Y-m-d H:i:s');
        $endAt       = Carbon::parse(($data['endDate'] ?? $data['startDate']) . ' ' . ($data['endTime'] ?? '23:59'))->format('Y-m-d H:i:s');
        if ($data['startTime'] && $data['endTime'] && ($data['startDate'] === ($data['endDate'] ?? $data['startDate'])) && $endAt <= $startAt) {
            abort(422, 'The end time must be after the start time.');
        }
        $eventId = $this->transactionWithUploadCleanup(function (array &$createdFiles) use ($data, $id, $startAt, $endAt): int {
            $existing    = $id ? DB::table('events')->where('event_id', $id)->first() : null;
            $bannerId    = $this->storeImage($data['banner'] ?? null, $createdFiles);
            $bannerValue = $data['banner'] ?? null;
            $values      = [
                'title'          => trim($data['title']),
                'campus_id'      => DB::table('campuses')->orderBy('campus_id')->value('campus_id'),
                'description'    => $this->sanitizeRichText($data['description'] ?? ''),
                'category_id'    => $data['categoryId'],
                'venue'          => $data['venue'] ?? null,
                'start_at'       => $startAt,
                'end_at'         => $endAt,
                'organizer'      => $data['organizer'] ?? null,
                'status'         => $data['state'],
                'cover_media_id' => str_starts_with((string) $bannerValue, 'data:')
                    ? $bannerId
                    : ($bannerValue === '' ? null : ($existing->cover_media_id ?? null)),
                'updated_at'     => now(),
            ];
            if (! $id) {
                $values['created_by'] = request()->user()?->user_id;
                $values['slug']       = $this->uniqueSlug('events', 'slug', $data['title']);
                $values['created_at'] = now();

                return DB::table('events')->insertGetId($values, 'event_id');
            }

            DB::table('events')->where('event_id', $id)->update($values);

            return $id;
        });

        return response()->json($this->eventJson($this->findEvent($eventId)), $request->isMethod('post') ? 201 : 200);
    }

    private function applyDatePreset($query, string $column, ?string $preset): void
    {
        if (! $preset) {
            return;
        }
        if ($preset === 'year') {
            $query->whereYear($column, now()->year);
            return;
        }
        $query->whereDate($column, '>=', today()->subDays((int) $preset - 1)->toDateString())
            ->whereDate($column, '<=', today()->toDateString());
    }

    private function eventStatus(array $event): string
    {
        if (in_array($event['state'], ['completed', 'cancelled', 'archived'], true)) {
            return $event['state'];
        }
        if ($event['state'] !== 'published') {
            return 'draft';
        }
        $now   = now();
        $start = Carbon::parse($event['startDate'] . ' ' . ($event['startTime'] ?: '00:00'));
        $end   = Carbon::parse($event['endDate'] . ' ' . ($event['endTime'] ?: '23:59'));

        if ($now->lt($start)) {
            return 'upcoming';
        }
        if ($now->gt($end)) {
            return 'completed';
        }

        return 'ongoing';
    }

    private function newsJson(object $row): array
    {
        return [
            'id'               => (int) $row->id,
            'title'            => $row->title,
            'categoryId'       => (string) ($row->category_id ?? ''),
            'category'         => $row->category_name ?? '',
            'author'           => $row->author_name ?: ($row->creator_name ?? ''),
            'campusId'         => $row->campus_id === null ? '' : (string) $row->campus_id,
            'campus'           => $row->campus_name ?: 'All Campuses',
            'publishedAt'      => $row->published_at ? Carbon::parse($row->published_at)->toDateString() : '',
            'status'           => $row->status,
            'shortDescription' => $row->summary ?? '',
            'content'          => $row->content ?? '',
            'image'            => $this->mediaUrl($row->cover_media_id),
        ];
    }

    private function announcementJson(object $row): array
    {
        $attachment = null;
        if ($row->document_id) {
            $attachment = [
                'name' => $row->file_name,
                'id'   => (int) $row->document_id,
                'size' => (int) ($row->file_size ?? 0),
                'type' => $row->mime_type ?? '',
                'url'  => $this->storedFileExists((string) $row->file_path) ? route('cms.document', ['id' => $row->document_id]) : '',
            ];
        }

        return [
            'id'          => (int) $row->id,
            'title'       => $row->title,
            'categoryId'  => (string) ($row->category_id ?? ''),
            'category'    => $row->category_name ?? '',
            'author'      => $row->author_name ?? '',
            'campusId'    => $row->campus_id === null ? '' : (string) $row->campus_id,
            'campus'      => $row->campus_name ?: 'All Campuses',
            'priority'    => $row->priority,
            'publishedAt' => $row->published_at ? Carbon::parse($row->published_at)->toDateString() : '',
            'expiresAt'   => $row->expires_at ? Carbon::parse($row->expires_at)->toDateString() : '',
            'status'      => $row->status,
            'content'     => $row->content,
            'attachment'  => $attachment,
        ];
    }

    private function eventJson(object $row): array
    {
        $start = Carbon::parse($row->start_at);
        $end   = $row->end_at ? Carbon::parse($row->end_at) : null;

        return [
            'id'           => (int) $row->id,
            'title'        => $row->title,
            'categoryId'   => (string) $row->categoryId,
            'categoryName' => $row->categoryName,
            'description'  => $row->description ?? '',
            'venue'        => $row->venue ?? '',
            'startDate'    => $start->toDateString(),
            'endDate'      => $end ? $end->toDateString() : $start->toDateString(),
            'startTime'    => $start->format('H:i'),
            'endTime'      => $end ? $end->format('H:i') : '',
            'organizer'    => $row->organizer ?? '',
            'state'        => in_array($row->status, ['completed', 'cancelled', 'archived'], true)
                ? $row->status
                : ($row->status === 'published' ? 'published' : 'draft'),
            'banner'       => $this->mediaUrl($row->cover_media_id),
        ];
    }

    private function eventCategoryJson(int $id): object
    {
        return DB::table('event_categories')->where('category_id', $id)->first(['category_id as id', 'name', 'description']);
    }

    private function findNews(int $id): object
    {
        $row = DB::table('news as n')
            ->leftJoin('campuses as c', 'c.campus_id', '=', 'n.campus_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'n.created_by')
            ->select([
                'n.news_id as id', 'n.title', 'n.author_name', 'u.full_name as creator_name',
                'n.excerpt as summary', 'n.content', 'n.status', 'n.published_at', 'n.campus_id',
                'c.name as campus_name', 'n.cover_media_id',
                DB::raw('(SELECT nc.category_id FROM news_category_map ncm JOIN news_categories nc ON nc.category_id = ncm.category_id WHERE ncm.news_id = n.news_id ORDER BY nc.category_id LIMIT 1) as category_id'),
                DB::raw('(SELECT nc.name FROM news_category_map ncm JOIN news_categories nc ON nc.category_id = ncm.category_id WHERE ncm.news_id = n.news_id ORDER BY nc.category_id LIMIT 1) as category_name'),
            ])->where('n.news_id', $id)->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    private function mediaJson(int $id): array
    {
        $row = DB::table('media')->where('media_id', $id)->first();
        abort_unless($row !== null, 404);

        return [
            'id' => (int) $row->media_id,
            'fileName' => $row->file_name,
            'mimeType' => $row->mime_type,
            'fileSize' => $row->file_size === null ? null : (int) $row->file_size,
            'altText' => $row->alt_text ?? '',
            'createdAt' => $row->created_at,
            'available' => $this->storedFileExists((string) $row->file_path),
            'url' => $this->mediaUrl((int) $row->media_id),
        ];
    }

    private function documentJson(int $id): array
    {
        $row = DB::table('documents')->where('document_id', $id)->first();
        abort_unless($row !== null, 404);

        return [
            'id' => (int) $row->document_id,
            'title' => $row->title ?? '',
            'fileName' => $row->file_name,
            'mimeType' => $row->mime_type,
            'fileSize' => $row->file_size === null ? null : (int) $row->file_size,
            'createdAt' => $row->created_at,
            'available' => $this->storedFileExists((string) $row->file_path),
            'url' => route('cms.document', ['id' => (int) $row->document_id]),
        ];
    }

    private function deleteStoredUpload(string $path): void
    {
        if (str_starts_with($path, 'private/')) {
            $diskPath = substr($path, strlen('private/'));
            if (Storage::disk('local')->exists($diskPath)) {
                abort_unless(Storage::disk('local')->delete($diskPath), 500, 'The uploaded file could not be removed.');
            }
            return;
        }
        if (! str_starts_with($path, '/storage/')) {
            return;
        }
        $diskPath = substr($path, strlen('/storage/'));
        if (Storage::disk('public')->exists($diskPath)) {
            abort_unless(Storage::disk('public')->delete($diskPath), 500, 'The uploaded file could not be removed.');
        }
    }

    private function findFaculty(int $id): object
    {
        $row = DB::table('faculty_staff as f')
            ->leftJoin('departments as d', 'd.department_id', '=', 'f.department_id')
            ->select([
                'f.faculty_id as id',
                DB::raw("TRIM(CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.middle_name, ''), ' ', COALESCE(f.last_name, ''))) as name"),
                'f.position', 'f.department_id as departmentId',
                'd.name as department', 'f.email', 'f.biography as summary',
            ])
            ->where('f.faculty_id', $id)
            ->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    private function findUser(int $id): object
    {
        $row = DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->select([
                'u.user_id as id', 'u.full_name as fullName', 'u.email',
                DB::raw($this->normalizedUserRoleExpression() . ' as role'), 'u.status',
            ])
            ->where('u.user_id', $id)
            ->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    private function normalizedUserRoleExpression(): string
    {
        return "COALESCE(NULLIF(u.role, ''), CASE LOWER(COALESCE(r.name, '')) " .
            "WHEN 'super administrator' THEN 'admin' " .
            "WHEN 'administrator' THEN 'admin' " .
            "WHEN 'editor' THEN 'editor' " .
            "WHEN 'author' THEN 'author' " .
            "ELSE LOWER(COALESCE(r.name, '')) END)";
    }

    private function findCollege(int $id): object
    {
        $row = DB::table('colleges as c')
            ->select([
                'c.college_id as id', 'c.name', 'c.slug', 'c.code', 'c.description',
                'c.dean_name as deanName', 'c.email as contactEmail', 'c.logo_media_id',
                'c.status',
                DB::raw('(SELECT COUNT(*) FROM departments d WHERE d.college_id = c.college_id) as departmentCount'),
                DB::raw('(SELECT COUNT(*) FROM programs p WHERE p.college_id = c.college_id) as programCount'),
            ])
            ->where('c.college_id', $id)
            ->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    private function collegeJson(object $row): array
    {
        return [
            'id'              => (int) $row->id,
            'name'            => $row->name,
            'slug'            => $row->slug,
            'code'            => $row->code,
            'description'     => $row->description ?? '',
            'deanName'        => $row->deanName ?? '',
            'contactEmail'    => $row->contactEmail ?? '',
            'image'           => $this->mediaUrl($row->logo_media_id ? (int) $row->logo_media_id : null),
            'status'          => $row->status,
            'departmentCount' => (int) $row->departmentCount,
            'programCount'    => (int) $row->programCount,
        ];
    }

    private function findProgram(int $id): object
    {
        $row = DB::table('programs as p')
            ->join('colleges as c', 'c.college_id', '=', 'p.college_id')
            ->join('departments as d', 'd.department_id', '=', 'p.department_id')
            ->select([
                'p.program_id as id', 'p.name', 'p.slug', 'p.code', 'p.degree_level as degreeLevel',
                'p.college_id as collegeId', 'c.name as collegeName', 'c.code as collegeCode',
                'p.department_id as departmentId', 'd.name as departmentName',
                'p.description', 'p.status',
            ])
            ->where('p.program_id', $id)
            ->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    private function findDepartment(int $id): object
    {
        $row = DB::table('departments as d')
            ->join('colleges as c', 'c.college_id', '=', 'd.college_id')
            ->leftJoin('faculty_staff as f', 'f.faculty_id', '=', 'd.head_faculty_id')
            ->select([
                'd.department_id as id', 'd.name', 'd.slug', 'd.code',
                'd.college_id as collegeId', 'c.name as collegeName', 'c.code as collegeCode',
                'd.head_faculty_id as headId',
                DB::raw("TRIM(CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.middle_name, ''), ' ', COALESCE(f.last_name, ''))) as headName"),
                'd.description', 'd.status',
                DB::raw('(SELECT COUNT(*) FROM programs p WHERE p.department_id = d.department_id) as programCount'),
            ])
            ->where('d.department_id', $id)
            ->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    private function findAnnouncement(int $id): object
    {
        $row = DB::table('announcements as a')
            ->leftJoin('campuses as c', 'c.campus_id', '=', 'a.campus_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'a.created_by')
            ->leftJoin('documents as d', 'd.document_id', '=', 'a.attachment_document_id')
            ->leftJoin('announcement_categories as ac', 'ac.category_id', '=', 'a.category_id')
            ->select([
                'a.announcement_id as id', 'a.title', 'a.content', 'a.priority', 'a.expires_at',
                'a.status', 'a.published_at', 'a.campus_id', 'c.name as campus_name', 'u.full_name as author_name',
                'd.document_id', 'd.file_name', 'd.file_path', 'd.mime_type', 'd.file_size',
                'a.category_id', 'ac.name as category_name',
            ])->where('a.announcement_id', $id)->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    private function findEvent(int $id): object
    {
        $row = DB::table('events as e')->join('event_categories as ec', 'ec.category_id', '=', 'e.category_id')
            ->select([
                'e.event_id as id', 'e.title', 'e.description', 'e.category_id as categoryId',
                'ec.name as categoryName', 'e.venue', 'e.start_at', 'e.end_at', 'e.organizer',
                'e.status', 'e.cover_media_id',
            ])->where('e.event_id', $id)->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    private function trimUniqueFields(Request $request, array $fields, array $nullableFields = []): void
    {
        $normalized = [];
        foreach ($fields as $field) {
            $value = $request->input($field);
            if (! is_string($value)) {
                continue;
            }
            $value = trim($value);
            $normalized[$field] = $value === '' && in_array($field, $nullableFields, true) ? null : $value;
        }
        $request->merge($normalized);
    }

    private function transactionWithUploadCleanup(callable $callback): mixed
    {
        $createdFiles = [];
        try {
            return DB::transaction(function () use ($callback, &$createdFiles) {
                return $callback($createdFiles);
            });
        } catch (\Throwable $exception) {
            foreach ($createdFiles as $file) {
                try {
                    $deleted = Storage::disk($file['disk'])->delete($file['path']);
                } catch (\Throwable $cleanupException) {
                    throw new \RuntimeException('A failed upload could not be cleaned up.', 0, $cleanupException);
                }
                if (! $deleted) {
                    throw new \RuntimeException('A failed upload could not be cleaned up.', 0, $exception);
                }
            }

            throw $exception;
        }
    }

    private function storeImage(?string $dataUrl, ?array &$createdFiles = null): ?int
    {
        if (! $dataUrl || ! str_starts_with($dataUrl, 'data:')) {
            return null;
        }
        if (! preg_match('#^data:image/(jpeg|png|gif|webp);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $matches)) {
            abort(422, 'The image format is invalid.');
        }
        $bytes = base64_decode($matches[2], true);
        abort_unless($bytes !== false && strlen($bytes) <= 5 * 1024 * 1024, 422, 'The image is invalid or exceeds 5 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true), 422, 'The image type is not allowed.');
        $imageInfo = @getimagesizefromstring($bytes);
        abort_unless($imageInfo !== false && $imageInfo[0] > 0 && $imageInfo[1] > 0
            && $imageInfo[0] * $imageInfo[1] <= 20000000, 422, 'The image dimensions are invalid or too large.');
        $encoder = [
            'image/jpeg' => 'imagejpeg',
            'image/png' => 'imagepng',
            'image/gif' => 'imagegif',
            'image/webp' => 'imagewebp',
        ][$mime];
        abort_unless(function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled') && function_exists($encoder),
            503, 'Image processing support is unavailable on this server.');
        $source = @imagecreatefromstring($bytes);
        abort_unless($source !== false, 422, 'The image cannot be decoded.');
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 1200 / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        abort_unless($canvas !== false, 503, 'Image processing support is unavailable on this server.');
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ob_start();
        if ($mime === 'image/jpeg') {
            $encodedSuccessfully = imagejpeg($canvas, null, 82);
        } elseif ($mime === 'image/png') {
            $encodedSuccessfully = imagepng($canvas);
        } elseif ($mime === 'image/gif') {
            $encodedSuccessfully = imagegif($canvas);
        } else {
            $encodedSuccessfully = imagewebp($canvas, null, 82);
        }
        $encodedBytes = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);
        abort_unless($encodedSuccessfully && is_string($encodedBytes) && $encodedBytes !== '', 422, 'The image could not be re-encoded.');
        $bytes = $encodedBytes;

        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
        $path      = 'media/' . Str::uuid() . '.' . $extension;
        abort_unless(Storage::disk('public')->put($path, $bytes), 500, 'The uploaded image could not be stored.');
        if ($createdFiles !== null) {
            $createdFiles[] = ['disk' => 'public', 'path' => $path];
        }
        $id = DB::table('media')->insertGetId([
            'file_name' => basename($path),
            'file_path' => '/storage/' . $path,
            'file_type' => $mime,
            'file_size_kb' => (int) ceil(strlen($bytes) / 1024),
            'mime_type' => $mime,
            'file_size' => strlen($bytes),
            'uploaded_by' => request()->user()?->user_id,
        ], 'media_id');

        return $id;
    }

    private function storeDocument(array $attachment, ?array &$createdFiles = null): int
    {
        if (! preg_match('#^data:([A-Za-z0-9.+/-]+);base64,([A-Za-z0-9+/=]+)$#', (string) ($attachment['data'] ?? ''), $matches)) {
            abort(422, 'Please reselect the attachment before saving.');
        }
        $bytes = base64_decode($matches[2], true);
        abort_unless($bytes !== false && strlen($bytes) <= 300 * 1024, 422, 'Attachments over 300 KB are not supported by this prototype.');
        $name      = basename((string) ($attachment['name'] ?? 'attachment'));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'png', 'jpg', 'jpeg'], true), 422, 'The attachment file type is not allowed.');
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
        $mime = $this->validatedDocumentMime($bytes, $extension, $detectedMime);
        abort_unless($mime !== null, 422, 'The file contents do not match a supported document type.');
        $path = 'documents/' . Str::uuid() . '-' . Str::slug(pathinfo($name, PATHINFO_FILENAME)) . '.' . $extension;
        abort_unless(Storage::disk('local')->put($path, $bytes), 500, 'The uploaded document could not be stored.');
        if ($createdFiles !== null) {
            $createdFiles[] = ['disk' => 'local', 'path' => $path];
        }

        $documentId = DB::table('documents')->insertGetId([
            'title'     => pathinfo($name, PATHINFO_FILENAME),
            'category_id' => DB::table('document_categories')->orderBy('category_id')->value('category_id'),
            'created_by' => request()->user()?->user_id,
            'file_name' => $name,
            'file_path' => 'private/' . $path,
            'mime_type' => $mime,
            'file_size' => strlen($bytes),
        ], 'document_id');
        if ($documentId) {
            DB::table('document_versions')->insert([
                'document_id' => $documentId,
                'uploaded_by' => request()->user()?->user_id,
                'version_no' => 1,
                'file_name' => $name,
                'file_path' => 'private/' . $path,
                'file_type' => $mime,
                'file_size_kb' => (int) ceil(strlen($bytes) / 1024),
                'created_at' => now(),
            ]);
        }
        return $documentId;
    }

    private function validatedDocumentMime(string $bytes, string $extension, string $detectedMime): ?string
    {
        $expectedMime = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ][$extension] ?? null;
        if ($expectedMime === null) {
            return null;
        }

        $openXmlEntries = [
            'docx' => ['word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'],
            'xlsx' => ['xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'],
            'pptx' => ['ppt/presentation.xml', 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml'],
        ];
        if (isset($openXmlEntries[$extension])) {
            if (! in_array($detectedMime, [$expectedMime, 'application/zip', 'application/x-zip', 'application/x-zip-compressed'], true)) {
                return null;
            }

            return $this->hasOpenXmlStructure($bytes, ...$openXmlEntries[$extension]) ? $expectedMime : null;
        }

        if (in_array($extension, ['doc', 'xls', 'ppt'], true)) {
            $oleSignature = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
            if (! str_starts_with($bytes, $oleSignature)
                || ! in_array($detectedMime, [$expectedMime, 'application/x-ole-storage'], true)) {
                return null;
            }

            return $expectedMime;
        }

        if ($detectedMime !== $expectedMime) {
            return null;
        }
        if ($extension === 'pdf' && ! str_starts_with($bytes, '%PDF-')) {
            return null;
        }
        if (in_array($extension, ['png', 'jpg', 'jpeg'], true) && @getimagesizefromstring($bytes) === false) {
            return null;
        }

        return $expectedMime;
    }

    private function hasOpenXmlStructure(string $bytes, string $requiredEntry, string $requiredContentType): bool
    {
        abort_unless(class_exists(\ZipArchive::class), 503, 'ZIP support is required to validate Office Open XML documents.');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'cms-openxml-');
        abort_unless($temporaryPath !== false, 500, 'A temporary file could not be created to validate the document.');

        try {
            abort_unless(file_put_contents($temporaryPath, $bytes) === strlen($bytes), 500, 'The document could not be prepared for validation.');
            $archive = new \ZipArchive();
            if ($archive->open($temporaryPath, \ZipArchive::RDONLY) !== true) {
                return false;
            }

            try {
                $contentTypesStat = $archive->statName('[Content_Types].xml');
                if (! is_array($contentTypesStat) || ($contentTypesStat['size'] ?? PHP_INT_MAX) > 65536
                    || $archive->locateName($requiredEntry) === false) {
                    return false;
                }
                $contentTypes = $archive->getFromName('[Content_Types].xml');

                return is_string($contentTypes) && str_contains($contentTypes, $requiredContentType);
            } finally {
                $archive->close();
            }
        } finally {
            if (is_file($temporaryPath) && ! unlink($temporaryPath)) {
                throw new \RuntimeException('The temporary document validation file could not be removed.');
            }
        }
    }

    private function storedFileResponse(string $path, ?string $mime, ?string $name, bool $download = true): Response
    {
        $headers = [
            'Content-Type'           => $mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if (str_starts_with($path, '/storage/')) {
            $diskPath = substr($path, strlen('/storage/'));
            abort_unless(Storage::disk('public')->exists($diskPath), 404);
            $stream = Storage::disk('public')->readStream($diskPath);
            abort_unless(is_resource($stream), 404);

            $sendStream = static function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            };

            return $download
                ? response()->streamDownload($sendStream, $name ?: basename($diskPath), $headers)
                : response()->stream($sendStream, 200, $headers);
        }
        if (str_starts_with($path, 'private/')) {
            $diskPath = substr($path, strlen('private/'));
            abort_unless(Storage::disk('local')->exists($diskPath), 404);
            $stream = Storage::disk('local')->readStream($diskPath);
            abort_unless(is_resource($stream), 404);
            $sendStream = static function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            };
            return $download
                ? response()->streamDownload($sendStream, $name ?: basename($diskPath), $headers)
                : response()->stream($sendStream, 200, $headers);
        }

        $uploadsRoot = realpath(base_path('../uploads'));
        $file        = realpath(base_path('../' . ltrim($path, '/')));
        abort_unless($uploadsRoot && $file && str_starts_with($file, $uploadsRoot . DIRECTORY_SEPARATOR), 404);

        $response = response()->file($file, $headers);
        if ($download && $name) {
            $response->setContentDisposition('attachment', $name);
        }

        return $response;
    }

    private function mediaUrl(?int $id): string
    {
        if (! $id) {
            return '';
        }
        $media = DB::table('media')->where('media_id', $id)->first(['file_path']);
        if (! $media || ! $this->storedFileExists((string) $media->file_path)) {
            return '';
        }

        return route('cms.media', ['id' => $id]);
    }

    private function sanitizeRichText(string $html): string
    {
        $clean = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><blockquote><h1><h2><h3>');
        $clean = preg_replace('/<a\b[^>]*>/i', '<a>', $clean) ?? $clean;
        $clean = preg_replace('/\s+(on[a-z]+|style|class|id)\s*=("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $clean) ?? $clean;

        return trim($clean);
    }

    private function storedFileExists(string $path): bool
    {
        if (str_starts_with($path, '/storage/')) {
            return Storage::disk('public')->exists(substr($path, strlen('/storage/')));
        }
        if (str_starts_with($path, 'private/')) {
            return Storage::disk('local')->exists(substr($path, strlen('private/')));
        }
        $uploadsRoot = realpath(base_path('../uploads'));
        $file        = realpath(base_path('../' . ltrim($path, '/')));

        return $uploadsRoot && $file && str_starts_with($file, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($file);
    }

    private function uniqueSlug(string $table, string $column, string $value): string
    {
        $slug      = Str::slug($value) ?: Str::random(10);
        $slug      = substr($slug, 0, 243);
        $candidate = $slug;
        $suffix    = 2;
        while (DB::table($table)->where($column, $candidate)->exists()) {
            $candidate = $slug . '-' . $suffix++;
        }

        return $candidate;
    }

    private function uniqueSlugExcept(string $table, string $column, string $value, string $idColumn, int $id): string
    {
        $slug = Str::slug($value) ?: Str::random(10);
        $slug = substr($slug, 0, 243);
        $candidate = $slug;
        $suffix = 2;
        while (DB::table($table)->where($column, $candidate)->where($idColumn, '<>', $id)->exists()) {
            $candidate = $slug . '-' . $suffix++;
        }

        return $candidate;
    }
}
