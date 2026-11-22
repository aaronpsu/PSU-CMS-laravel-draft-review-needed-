<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    public function options(): JsonResponse
    {
        return response()->json([
            'campuses' => DB::table('campuses')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['campus_id as id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fullName' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'subject' => ['required', 'string', 'min:3', 'max:220'],
            'campusId' => ['nullable', 'integer', 'exists:campuses,campus_id'],
            'type' => ['required', Rule::in(['inquiry', 'feedback'])],
            'rating' => ['required_if:type,feedback', 'nullable', 'integer', 'between:1,5'],
            'category' => ['required_if:type,feedback', 'nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        if (trim((string) ($data['website'] ?? '')) !== '') {
            return response()->json(['submitted' => true]);
        }

        $key = 'cms-contact:' . hash('sha256', (string) $request->ip());
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Too many submissions. Please wait before trying again.');
        RateLimiter::hit($key, 15 * 60);

        $common = [
            'campus_id' => $data['campusId'] ?? null,
            'full_name' => trim($data['fullName']),
            'email' => strtolower(trim($data['email'])),
            'message' => trim($data['message']),
        ];

        if ($data['type'] === 'feedback') {
            $id = DB::table('feedback_submissions')->insertGetId($common + [
                'category' => trim($data['category']),
                'rating' => $data['rating'],
                'status' => 'new',
                'created_at' => now(),
            ], 'feedback_id');
            $reference = 'FB-' . $id;
        } else {
            $id = DB::table('contact_submissions')->insertGetId($common + [
                'subject' => trim($data['subject']),
                'status' => 'unread',
                'received_at' => now(),
            ], 'submission_id');
            $reference = 'MSG-' . $id;
        }

        return response()->json(['submitted' => true, 'reference' => $reference], 201);
    }

    public function index(): JsonResponse
    {
        $contacts = DB::table('contact_submissions as c')
            ->leftJoin('campuses as ca', 'ca.campus_id', '=', 'c.campus_id')
            ->leftJoin('departments as d', 'd.department_id', '=', 'c.department_id')
            ->orderByDesc('c.received_at')
            ->get([
                'c.submission_id', 'c.full_name', 'c.email', 'c.subject', 'c.message',
                'c.status', 'c.reply_subject', 'c.reply_message', 'c.received_at', 'c.replied_at', 'ca.name as campus_name',
                'd.name as department_name',
            ])
            ->map(fn (object $row): array => $this->contactJson($row));

        $feedback = DB::table('feedback_submissions as f')
            ->leftJoin('campuses as ca', 'ca.campus_id', '=', 'f.campus_id')
            ->orderByDesc('f.created_at')
            ->get([
                'f.feedback_id', 'f.full_name', 'f.email', 'f.category', 'f.rating',
                'f.message', 'f.status', 'f.created_at', 'ca.name as campus_name',
            ])
            ->map(fn (object $row): array => $this->feedbackJson($row));

        return response()->json([
            'data' => $contacts->concat($feedback)->sortByDesc('submittedAt')->values(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        [$type, $key] = $this->parseId($id);
        $message = $type === 'contact'
            ? $this->findContact($key)
            : $this->findFeedback($key);

        abort_unless($message !== null, 404, 'Message not found.');

        return response()->json($message);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        [$type, $key] = $this->parseId($id);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['unread', 'read', 'responded'])],
            'reply.subject' => ['sometimes', 'required', 'string', 'max:220'],
            'reply.body' => ['sometimes', 'required', 'string', 'min:5', 'max:5000'],
        ]);
        $hasReply = isset($data['reply']);

        if ($type === 'contact') {
            abort_unless(DB::table('contact_submissions')->where('submission_id', $key)->exists(), 404, 'Message not found.');
            $values = [];
            if (isset($data['status'])) $values['status'] = $data['status'];
            if ($hasReply) {
                $values['status'] = 'responded';
                $values['reply_subject'] = trim($data['reply']['subject']);
                $values['reply_message'] = trim($data['reply']['body']);
                $values['replied_at'] = now();
            }
            if ($values !== []) {
                DB::table('contact_submissions')->where('submission_id', $key)->update($values);
            }
            $message = $this->findContact($key);
        } else {
            abort_unless(! $hasReply, 422, 'Feedback replies are not supported.');
            abort_unless(DB::table('feedback_submissions')->where('feedback_id', $key)->exists(), 404, 'Feedback not found.');
            if (isset($data['status'])) {
                $status = $data['status'] === 'unread' ? 'new' : 'reviewed';
                DB::table('feedback_submissions')->where('feedback_id', $key)->update(['status' => $status]);
            }
            $message = $this->findFeedback($key);
        }

        return response()->json($message);
    }

    public function destroy(string $id): JsonResponse
    {
        [$type, $key] = $this->parseId($id);
        $table = $type === 'contact' ? 'contact_submissions' : 'feedback_submissions';
        $primaryKey = $type === 'contact' ? 'submission_id' : 'feedback_id';
        $deleted = DB::table($table)->where($primaryKey, $key)->delete();
        abort_unless($deleted > 0, 404, 'Message not found.');

        return response()->json(['deleted' => true]);
    }

    private function contactJson(object $row): array
    {
        $reply = $row->reply_message ? [
            'subject' => $row->reply_subject ?: 'Re: ' . $row->subject,
            'body' => $row->reply_message,
            'sentAt' => $this->isoDate($row->replied_at ?? null),
        ] : null;

        return [
            'id' => 'c-' . $row->submission_id,
            'fullName' => $row->full_name,
            'email' => $row->email,
            'subject' => $row->subject,
            'campusDepartment' => $row->campus_name ?: ($row->department_name ?? ''),
            'message' => $row->message,
            'status' => $row->status,
            'type' => 'inquiry',
            'rating' => null,
            'category' => '',
            'submittedAt' => $this->isoDate($row->received_at),
            'reply' => $reply,
        ];
    }

    private function feedbackJson(object $row): array
    {
        return [
            'id' => 'f-' . $row->feedback_id,
            'fullName' => $row->full_name,
            'email' => $row->email ?? '',
            'subject' => $row->category . ' feedback',
            'campusDepartment' => $row->campus_name ?? '',
            'message' => $row->message,
            'status' => in_array($row->status, ['new', 'unread'], true) ? 'unread' : 'read',
            'type' => 'feedback',
            'rating' => (int) $row->rating,
            'category' => $row->category,
            'submittedAt' => $this->isoDate($row->created_at),
            'reply' => null,
        ];
    }

    private function findContact(int $id): ?array
    {
        $row = DB::table('contact_submissions as c')
            ->leftJoin('campuses as ca', 'ca.campus_id', '=', 'c.campus_id')
            ->leftJoin('departments as d', 'd.department_id', '=', 'c.department_id')
            ->where('c.submission_id', $id)
            ->first([
                'c.submission_id', 'c.full_name', 'c.email', 'c.subject', 'c.message',
                'c.status', 'c.reply_subject', 'c.reply_message', 'c.received_at', 'c.replied_at',
                'ca.name as campus_name', 'd.name as department_name',
            ]);

        return $row ? $this->contactJson($row) : null;
    }

    private function findFeedback(int $id): ?array
    {
        $row = DB::table('feedback_submissions as f')
            ->leftJoin('campuses as ca', 'ca.campus_id', '=', 'f.campus_id')
            ->where('f.feedback_id', $id)
            ->first([
                'f.feedback_id', 'f.full_name', 'f.email', 'f.category', 'f.rating',
                'f.message', 'f.status', 'f.created_at', 'ca.name as campus_name',
            ]);

        return $row ? $this->feedbackJson($row) : null;
    }

    private function parseId(string $id): array
    {
        abort_unless(preg_match('/^([cf])-(\d+)$/', $id, $matches) === 1, 404, 'Message not found.');

        return [$matches[1] === 'c' ? 'contact' : 'feedback', (int) $matches[2]];
    }

    private function isoDate(?string $value): string
    {
        return $value ? str_replace(' ', 'T', $value) : '';
    }
}
