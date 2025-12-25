<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use App\Exceptions\BusinessException;

class NotificationService
{
    /**
     * إنشاء إشعار جديد
     */
    public function createNotification(array $data): Notification
    {
        return DB::transaction(function () use ($data) {
            $notification = Notification::create($data);
            
            // إرسال الإشعار في الوقت الفعلي إذا كان المستخدم متصلاً
            $this->sendRealTimeNotification($notification);
            
            return $notification;
        });
    }

    /**
     * إنشاء إشعار للمستخدم
     */
    public function createUserNotification(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): Notification {
        return $this->createNotification([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
        ]);
    }

    /**
     * إنشاء إشعار متعدد المستخدمين
     */
    public function createBulkNotification(
        array $userIds,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): array {
        $notifications = [];
        
        DB::transaction(function () use ($userIds, $type, $title, $message, $data, &$notifications) {
            foreach ($userIds as $userId) {
                $notification = Notification::create([
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                    'is_read' => false,
                ]);
                
                $notifications[] = $notification;
                $this->sendRealTimeNotification($notification);
            }
        });
        
        return $notifications;
    }

    /**
     * الحصول على إشعارات المستخدم
     */
    public function getUserNotifications(
        User $user,
        bool $unreadOnly = false,
        int $perPage = 20
    ) {
        $query = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');
        
        if ($unreadOnly) {
            $query->where('is_read', false);
        }
        
        return $query->paginate($perPage);
    }

    /**
     * تحديد الإشعار كمقروء
     */
    public function markAsRead(string $notificationUuid, User $user): Notification
    {
        $notification = Notification::where('uuid', $notificationUuid)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        if (!$notification->is_read) {
            $notification->update(['is_read' => true, 'read_at' => now()]);
        }
        
        return $notification;
    }

    /**
     * تحديد جميع إشعارات المستخدم كمقروءة
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * حذف الإشعار
     */
    public function deleteNotification(string $notificationUuid, User $user): bool
    {
        $notification = Notification::where('uuid', $notificationUuid)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        return $notification->delete();
    }

    /**
     * الحصول على عدد الإشعارات غير المقروءة
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * إنشاء إشعارات تلقائية للعميل
     */
    public function createClientFollowUpNotification(User $agent, string $clientName, \DateTimeInterface $followUpDate): Notification
    {
        return $this->createUserNotification(
            $agent,
            'client_follow_up',
            'متابعة عميل',
            "يجب متابعة العميل {$clientName} بتاريخ " . $followUpDate->format('Y-m-d'),
            [
                'action' => 'client_follow_up',
                'follow_up_date' => $followUpDate->format('Y-m-d H:i:s'),
                'client_name' => $clientName,
            ]
        );
    }

    /**
     * إنشاء إشعارات تلقائية للعقار
     */
    public function createPropertyStatusNotification(User $user, string $propertyTitle, string $oldStatus, string $newStatus): Notification
    {
        return $this->createUserNotification(
            $user,
            'property_status_changed',
            'تغيير حالة عقار',
            "تم تغيير حالة العقار {$propertyTitle} من {$oldStatus} إلى {$newStatus}",
            [
                'action' => 'property_status_changed',
                'property_title' => $propertyTitle,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]
        );
    }

    /**
     * إنشاء إشعار تعيين عميل
     */
    public function createClientAssignmentNotification(User $agent, string $clientName): Notification
    {
        return $this->createUserNotification(
            $agent,
            'client_assigned',
            'تعيين عميل جديد',
            "تم تعيين العميل {$clientName} لك",
            [
                'action' => 'client_assigned',
                'client_name' => $clientName,
            ]
        );
    }

    /**
     * إرسال إشعار في الوقت الفعلي
     */
    private function sendRealTimeNotification(Notification $notification): void
    {
        // سيتم تنفيذه لاحقاً باستخدام WebSockets أو Pusher
        // حالياً: فقط تسجيل في السجل
        \Log::info('Real-time notification sent', [
            'user_id' => $notification->user_id,
            'type' => $notification->type,
            'title' => $notification->title,
        ]);
    }

    /**
     * إرسال إشعارات مجدولة
     */
    public function sendScheduledNotifications(): void
    {
        // إشعارات متابعة العملاء
        $this->sendClientFollowUpNotifications();
        
        // إشعارات تجديد العقارات
        $this->sendPropertyRenewalNotifications();
        
        // إشعارات التقارير الأسبوعية
        $this->sendWeeklyReportNotifications();
    }

    /**
     * إرسال إشعارات متابعة العملاء
     */
    private function sendClientFollowUpNotifications(): void
    {
        // الحصول على العملاء الذين يحتاجون متابعة اليوم
        // (سيتم تفعيله لاحقاً)
    }

    /**
     * إرسال إشعارات تجديد العقارات
     */
    private function sendPropertyRenewalNotifications(): void
    {
        // الحصول على العقارات التي تحتاج تجديد
        // (سيتم تفعيله لاحقاً)
    }

    /**
     * إرسال إشعارات التقارير الأسبوعية
     */
    private function sendWeeklyReportNotifications(): void
    {
        // إرسال تقارير أداء أسبوعية للمديرين
        // (سيتم تفعيله لاحقاً)
    }

    /**
     * تنظيف الإشعارات القديمة
     */
    public function cleanupOldNotifications(int $days = 90): int
    {
        $date = now()->subDays($days);
        
        return Notification::where('created_at', '<', $date)
            ->delete();
    }
}
