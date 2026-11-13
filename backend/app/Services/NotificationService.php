<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NotificationService
{
    public const ALLOWED_RELATED_TYPES = ['job', 'application', 'interview'];

    /**
     * NOTI-001: Tạo thông báo nội bộ trên website (gọi bên trong DB::transaction của sự kiện nghiệp vụ).
     */
    public function notifyUser(int $userId, string $event, array $payload): Notification
    {
        $relatedType = $payload['related_type'] ?? null;
        if ($relatedType !== null && !in_array($relatedType, self::ALLOWED_RELATED_TYPES, true)) {
            throw new InvalidArgumentException("Loại tài nguyên liên kết không hợp lệ: {$relatedType}");
        }

        $rawContent = (string) ($payload['content'] ?? '');
        // Loại bỏ thẻ HTML thô để chống Stored XSS theo Data Dictionary 6.3.21
        $cleanContent = trim(strip_tags($rawContent));

        return Notification::create([
            'user_id'      => $userId,
            'event_type'   => trim($event),
            'content'      => $cleanContent,
            'related_type' => $relatedType,
            'related_id'   => isset($payload['related_id']) ? (int) $payload['related_id'] : null,
            'read_at'      => null,
            'created_at'   => $payload['created_at'] ?? now(),
        ]);
    }

    /**
     * NOTI-002 (API-NOTI-01): Lấy danh sách thông báo cá nhân có lọc & phân trang kèm unread_count.
     */
    public function index(User $actor, array $filters = []): array
    {
        $readFilter = $filters['read_filter'] ?? 'all';
        $pageSize = max(1, min(50, (int) ($filters['page_size'] ?? 15)));

        $query = Notification::where('user_id', $actor->id);

        if ($readFilter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($readFilter === 'read') {
            $query->whereNotNull('read_at');
        }

        $paginator = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($pageSize);

        $unreadCount = Notification::where('user_id', $actor->id)
            ->whereNull('read_at')
            ->count();

        return [
            'paginator'    => $paginator,
            'unread_count' => $unreadCount,
        ];
    }

    /**
     * NOTI-003 (API-NOTI-02): Đánh dấu 1 thông báo đã đọc (idempotent + chống IDOR).
     */
    public function markRead(User $actor, int $id): Notification
    {
        return DB::transaction(function () use ($actor, $id) {
            /** @var Notification $notification */
            $notification = Notification::where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $notification->user_id !== (int) $actor->id) {
                throw new AuthorizationException('Bạn không có quyền thao tác trên thông báo này.');
            }

            // Chỉ ghi read_at nếu đang null; nếu đã đọc rồi giữ nguyên (idempotent)
            if ($notification->read_at === null) {
                $notification->read_at = now();
                $notification->save();
            }

            return $notification->fresh();
        });
    }

    /**
     * NOTI-004 (API-NOTI-03): Đánh dấu toàn bộ thông báo chưa đọc của người dùng hiện hành thành đã đọc.
     */
    public function markAllRead(User $actor): int
    {
        return Notification::where('user_id', $actor->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
