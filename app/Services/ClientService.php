<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use App\Models\Property;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\Priority;
use Illuminate\Support\Facades\DB;
use App\Exceptions\BusinessException;

class ClientService extends BaseService
{
    public function __construct()
    {
        $this->model = Client::class;
    }

    /**
     * إنشاء عميل جديد
     */
    public function createClient(array $data, User $creator): Client
    {
        return $this->transaction(function () use ($data, $creator) {
            // تعيين القيم الافتراضية
            $data['created_by'] = $creator->id;
            $data['status'] = $data['status'] ?? ClientStatus::LEAD->value;
            $data['priority'] = $data['priority'] ?? Priority::MEDIUM->value;

            // التحقق من تفرد البريد الإلكتروني
            if (!empty($data['email']) && $this->emailExists($data['email'])) {
                throw new BusinessException('البريد الإلكتروني مسجل بالفعل', 400);
            }

            // التحقق من تفرد رقم الهاتف
            if (!empty($data['phone']) && $this->phoneExists($data['phone'])) {
                throw new BusinessException('رقم الهاتف مسجل بالفعل', 400);
            }

            // إذا تم تعيين وسيط، التحقق من أنه وسيط
            if (!empty($data['assigned_agent_id'])) {
                $agent = User::find($data['assigned_agent_id']);
                if (!$agent || $agent->role !== 'agent') {
                    throw new BusinessException('المستخدم المحدد ليس وسيطاً', 400);
                }
            }

            // إنشاء العميل
            $client = Client::create($data);

            // جدولة المتابعة الأولى
            $client->scheduleAutoFollowUp();

            // تسجيل الحدث
            $client->logAudit('created', $client->toArray());

            return $client;
        });
    }

    /**
     * تحديث بيانات العميل
     */
    public function updateClient(string $uuid, array $data, User $updater): Client
    {
        return $this->transaction(function () use ($uuid, $data, $updater) {
            $client = $this->findByUuidOrFail($uuid);

            // التحقق من صلاحية التعديل
            $this->checkUpdatePermission($client, $updater);

            // حفظ البيانات القديمة
            $oldData = $client->toArray();

            // التحقق من تفرد البريد الإلكتروني
            if (!empty($data['email']) && $data['email'] !== $client->email) {
                if ($this->emailExists($data['email'], $client->id)) {
                    throw new BusinessException('البريد الإلكتروني مسجل بالفعل', 400);
                }
            }

            // التحقق من تفرد رقم الهاتف
            if (!empty($data['phone']) && $data['phone'] !== $client->phone) {
                if ($this->phoneExists($data['phone'], $client->id)) {
                    throw new BusinessException('رقم الهاتف مسجل بالفعل', 400);
                }
            }

            // تحديث العميل
            $client->update($data);

            // تسجيل التغييرات
            $client->logAudit('updated', $client->getChanges(), $oldData);

            return $client->fresh();
        });
    }

    /**
     * تغيير حالة العميل
     */
    public function changeClientStatus(string $uuid, ClientStatus $status, User $changer, ?string $reason = null): Client
    {
        return $this->transaction(function () use ($uuid, $status, $changer, $reason) {
            $client = $this->findByUuidOrFail($uuid);

            // تغيير الحالة
            if ($status === ClientStatus::CLIENT) {
                $client->convertToClient();
            } elseif ($status === ClientStatus::PROSPECT) {
                $client->convertToProspect();
            } else {
                $client->update(['status' => $status->value]);
            }

            // تسجيل تغيير الحالة
            $client->logAudit('status_changed', [
                'new_status' => $status->value,
                'old_status' => $client->getOriginal('status'),
                'changed_by' => $changer->id,
                'reason' => $reason,
            ]);

            return $client->fresh();
        });
    }

    /**
     * تعيين وسيط للعميل
     */
    public function assignAgent(string $clientUuid, string $agentUuid, User $assigner, ?string $reason = null): Client
    {
        return $this->transaction(function () use ($clientUuid, $agentUuid, $assigner, $reason) {
            $client = $this->findByUuidOrFail($clientUuid);
            $agent = User::where('uuid', $agentUuid)->firstOrFail();

            // التحقق من أن المستخدم وسيط
            if ($agent->role !== 'agent') {
                throw new BusinessException('المستخدم المحدد ليس وسيطاً', 400);
            }

            // التحقق من صلاحية التعيين
            $this->checkAssignmentPermission($client, $assigner);

            $oldAgentId = $client->assigned_agent_id;

            // تعيين الوسيط
            $client->update(['assigned_agent_id' => $agent->id]);

            // تسجيل التعيين
            $client->logAudit('agent_assigned', [
                'new_agent_id' => $agent->id,
                'old_agent_id' => $oldAgentId,
                'assigned_by' => $assigner->id,
                'reason' => $reason,
            ]);

            return $client->fresh();
        });
    }

    /**
     * تحديث آخر اتصال مع العميل
     */
    public function updateLastContact(string $uuid, User $contactedBy, ?string $notes = null): Client
    {
        return $this->transaction(function () use ($uuid, $contactedBy, $notes) {
            $client = $this->findByUuidOrFail($uuid);

            // تحديث آخر اتصال
            $client->markAsContacted();

            // إضافة ملاحظات إذا وجدت
            if ($notes) {
                $client->addNote($notes, $contactedBy->id);
            }

            // تسجيل الاتصال
            $client->logAudit('contacted', [
                'contacted_by' => $contactedBy->id,
                'notes' => $notes,
                'next_follow_up_at' => $client->next_follow_up_at,
            ]);

            return $client->fresh();
        });
    }

    /**
     * جدولة متابعة للعميل
     */
    public function scheduleFollowUp(string $uuid, \DateTimeInterface $date, User $scheduler, ?string $reason = null): Client
    {
        return $this->transaction(function () use ($uuid, $date, $scheduler, $reason) {
            $client = $this->findByUuidOrFail($uuid);

            // جدولة المتابعة
            $client->scheduleFollowUp($date);

            // تسجيل الجدولة
            $client->logAudit('follow_up_scheduled', [
                'scheduled_by' => $scheduler->id,
                'follow_up_date' => $date->format('Y-m-d H:i:s'),
                'reason' => $reason,
            ]);

            return $client->fresh();
        });
    }

    /**
     * البحث المتقدم في العملاء
     */
    public function advancedSearch(array $filters = [], int $perPage = 15)
    {
        $query = Client::query();

        // تطبيق الفلاتر الأساسية
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['city'])) {
            $query->where('city', 'ILIKE', "%{$filters['city']}%");
        }

        if (!empty($filters['assigned_agent_id'])) {
            $query->where('assigned_agent_id', $filters['assigned_agent_id']);
        }

        // تطبيق فلاتر الميزانية
        if (!empty($filters['min_budget'])) {
            $query->where('max_budget', '>=', $filters['min_budget'])
                  ->orWhereNull('max_budget');
        }

        if (!empty($filters['max_budget'])) {
            $query->where('min_budget', '<=', $filters['max_budget'])
                  ->orWhereNull('min_budget');
        }

        // تطبيق فلاتر الغرف
        if (!empty($filters['min_bedrooms'])) {
            $query->where('max_bedrooms', '>=', $filters['min_bedrooms'])
                  ->orWhereNull('max_bedrooms');
        }

        // تطبيق البحث النصي
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('first_name', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('last_name', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('email', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('phone', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('code', 'ILIKE', "%{$filters['search']}%");
            });
        }

        // تطبيق فلاتر المتابعة
        if (!empty($filters['needs_follow_up'])) {
            $query->whereNotNull('next_follow_up_at')
                  ->where('next_follow_up_at', '<=', now());
        }

        if (!empty($filters['days_since_last_contact'])) {
            $date = now()->subDays($filters['days_since_last_contact']);
            $query->where('last_contacted_at', '<=', $date)
                  ->orWhereNull('last_contacted_at');
        }

        // تطبيق الترتيب
        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * الحصول على العملاء الذين يحتاجون متابعة
     */
    public function getClientsNeedingFollowUp(?string $agentUuid = null, int $perPage = 15)
    {
        $query = Client::whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now())
            ->whereNotIn('status', [ClientStatus::INACTIVE->value, ClientStatus::BLACKLISTED->value]);

        if ($agentUuid) {
            $agent = User::where('uuid', $agentUuid)->firstOrFail();
            $query->where('assigned_agent_id', $agent->id);
        }

        return $query->orderBy('next_follow_up_at', 'asc')
            ->paginate($perPage);
    }

    /**
     * الحصول على العملاء الجدد
     */
    public function getNewClients(int $days = 7, ?string $agentUuid = null)
    {
        $date = now()->subDays($days);

        $query = Client::where('created_at', '>=', $date);

        if ($agentUuid) {
            $agent = User::where('uuid', $agentUuid)->firstOrFail();
            $query->where('assigned_agent_id', $agent->id);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * الحصول على إحصائيات العملاء
     */
    public function getClientStats(?string $agentUuid = null): array
    {
        $query = Client::query();

        if ($agentUuid) {
            $agent = User::where('uuid', $agentUuid)->firstOrFail();
            $query->where('assigned_agent_id', $agent->id);
        }

        $total = $query->count();
        $leads = (clone $query)->where('status', ClientStatus::LEAD->value)->count();
        $prospects = (clone $query)->where('status', ClientStatus::PROSPECT->value)->count();
        $clients = (clone $query)->where('status', ClientStatus::CLIENT->value)->count();
        $inactive = (clone $query)->where('status', ClientStatus::INACTIVE->value)->count();
        $blacklisted = (clone $query)->where('status', ClientStatus::BLACKLISTED->value)->count();

        // إحصائيات حسب النوع
        $byType = (clone $query)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->type => $item->count];
            })
            ->toArray();

        // إحصائيات حسب الأولوية
        $byPriority = (clone $query)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->priority => $item->count];
            })
            ->toArray();

        // إحصائيات التحويل
        $conversionRate = $total > 0 ? (($clients + $prospects) / $total) * 100 : 0;

        return [
            'total' => $total,
            'leads' => $leads,
            'prospects' => $prospects,
            'clients' => $clients,
            'inactive' => $inactive,
            'blacklisted' => $blacklisted,
            'conversion_rate' => $conversionRate,
            'by_type' => $byType,
            'by_priority' => $byPriority,
            'needs_follow_up' => $this->getClientsNeedingFollowUp($agentUuid)->total(),
        ];
    }

    /**
     * مطابقة العميل مع العقارات المناسبة
     */
    public function matchClientWithProperties(string $clientUuid, int $limit = 10)
    {
        $client = $this->findByUuidOrFail($clientUuid);
        
        // الحصول على العقارات المتاحة
        $properties = Property::where('status', 'available')->get();
        
        // حساب درجة المطابقة لكل عقار
        $matchedProperties = $properties->map(function ($property) use ($client) {
            return [
                'property' => $property,
                'match_score' => $client->getMatchScore($property),
                'match_details' => $this->getMatchDetails($client, $property),
            ];
        })
        ->filter(function ($item) {
            return $item['match_score'] > 0;
        })
        ->sortByDesc('match_score')
        ->take($limit)
        ->values();

        // تسجيل عملية المطابقة
        $client->logAudit('property_match', [
            'matched_properties_count' => $matchedProperties->count(),
            'match_criteria_used' => [
                'budget' => $client->min_budget || $client->max_budget,
                'location' => !empty($client->preferred_locations),
                'bedrooms' => $client->min_bedrooms || $client->max_bedrooms,
                'property_type' => !empty($client->preferred_property_types),
            ],
        ]);

        return $matchedProperties;
    }

    /**
     * الحصول على تفاصيل المطابقة
     */
    private function getMatchDetails(Client $client, Property $property): array
    {
        $details = [];

        // مطابقة الميزانية
        if ($client->min_budget || $client->max_budget) {
            $details['budget'] = $client->matchesBudget($property->price_egp) ? 'متطابق' : 'غير متطابق';
        }

        // مطابقة الموقع
        if (!empty($client->preferred_locations)) {
            $details['location'] = in_array($property->city, $client->preferred_locations) ? 'متطابق' : 'غير متطابق';
        }

        // مطابقة عدد الغرف
        if ($client->min_bedrooms || $client->max_bedrooms) {
            $details['bedrooms'] = $client->matchesBedrooms($property->bedrooms) ? 'متطابق' : 'غير متطابق';
        }

        // مطابقة نوع العقار
        if (!empty($client->preferred_property_types)) {
            $details['property_type'] = in_array($property->type, $client->preferred_property_types) ? 'متطابق' : 'غير متطابق';
        }

        return $details;
    }

    /**
     * إضافة ملاحظة للعميل
     */
    public function addNote(string $uuid, string $note, User $author): Client
    {
        return $this->transaction(function () use ($uuid, $note, $author) {
            $client = $this->findByUuidOrFail($uuid);

            // إضافة الملاحظة
            $client->addNote($note, $author->id);

            // تسجيل إضافة الملاحظة
            $client->logAudit('note_added', [
                'added_by' => $author->id,
                'note_preview' => substr($note, 0, 100) . (strlen($note) > 100 ? '...' : ''),
            ]);

            return $client->fresh();
        });
    }

    /**
     * إضافة علامة للعميل
     */
    public function addTag(string $uuid, string $tag, User $addedBy): Client
    {
        return $this->transaction(function () use ($uuid, $tag, $addedBy) {
            $client = $this->findByUuidOrFail($uuid);

            // إضافة العلامة
            $client->addTag($tag);

            // تسجيل إضافة العلامة
            $client->logAudit('tag_added', [
                'added_by' => $addedBy->id,
                'tag' => $tag,
            ]);

            return $client->fresh();
        });
    }

    /**
     * إزالة علامة من العميل
     */
    public function removeTag(string $uuid, string $tag, User $removedBy): Client
    {
        return $this->transaction(function () use ($uuid, $tag, $removedBy) {
            $client = $this->findByUuidOrFail($uuid);

            // إزالة العلامة
            $client->removeTag($tag);

            // تسجيل إزالة العلامة
            $client->logAudit('tag_removed', [
                'removed_by' => $removedBy->id,
                'tag' => $tag,
            ]);

            return $client->fresh();
        });
    }

    /**
     * الحصول على تقرير العملاء
     */
    public function getClientsReport(array $filters = []): array
    {
        $query = Client::query();

        // تطبيق فلاتر التاريخ
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        // تطبيق فلاتر النوع
        if (!empty($filters['types'])) {
            $query->whereIn('type', (array) $filters['types']);
        }

        // تطبيق فلاتر المدينة
        if (!empty($filters['cities'])) {
            $query->whereIn('city', (array) $filters['cities']);
        }

        // تطبيق فلاتر الوسيط
        if (!empty($filters['agent_id'])) {
            $query->where('assigned_agent_id', $filters['agent_id']);
        }

        // تجميع البيانات
        $totalClients = $query->count();
        $convertedToClient = (clone $query)->where('status', ClientStatus::CLIENT->value)->count();
        $conversionRate = $totalClients > 0 ? ($convertedToClient / $totalClients) * 100 : 0;

        // تجميع حسب النوع
        $byType = (clone $query)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->type => $item->count];
            })
            ->toArray();

        // تجميع حسب الحالة
        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => $item->count];
            })
            ->toArray();

        // تجميع حسب المدينة
        $byCity = (clone $query)
            ->selectRaw('city, COUNT(*) as count')
            ->groupBy('city')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->city => $item->count];
            })
            ->toArray();

        // تجميع حسب المصدر
        $bySource = (clone $query)
            ->selectRaw('source, COUNT(*) as count')
            ->groupBy('source')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->source => $item->count];
            })
            ->toArray();

        // إحصائيات الميزانية
        $avgMinBudget = $query->avg('min_budget');
        $avgMaxBudget = $query->avg('max_budget');

        return [
            'summary' => [
                'total_clients' => $totalClients,
                'converted_to_client' => $convertedToClient,
                'conversion_rate' => $conversionRate,
                'avg_min_budget' => $avgMinBudget,
                'avg_max_budget' => $avgMaxBudget,
                'period' => [
                    'start_date' => $filters['start_date'] ?? null,
                    'end_date' => $filters['end_date'] ?? null,
                ],
            ],
            'by_type' => $byType,
            'by_status' => $byStatus,
            'by_city' => $byCity,
            'by_source' => $bySource,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * التحقق من وجود البريد الإلكتروني
     */
    private function emailExists(string $email, ?int $excludeClientId = null): bool
    {
        $query = Client::where('email', $email);

        if ($excludeClientId) {
            $query->where('id', '!=', $excludeClientId);
        }

        return $query->exists();
    }

    /**
     * التحقق من وجود رقم الهاتف
     */
    private function phoneExists(string $phone, ?int $excludeClientId = null): bool
    {
        $query = Client::where('phone', $phone);

        if ($excludeClientId) {
            $query->where('id', '!=', $excludeClientId);
        }

        return $query->exists();
    }

    /**
     * التحقق من صلاحية التعديل
     */
    private function checkUpdatePermission(Client $client, User $user): void
    {
        $canEdit = false;

        // المدير العام والإداريين يمكنهم تعديل كل شيء
        if ($user->is_admin) {
            $canEdit = true;
        }
        // الوسيط المسؤول يمكنه التعديل
        elseif ($client->assigned_agent_id === $user->id) {
            $canEdit = true;
        }
        // منشئ السجل يمكنه التعديل
        elseif ($client->created_by === $user->id) {
            $canEdit = true;
        }

        if (!$canEdit) {
            throw new BusinessException('غير مصرح لك بتعديل هذا العميل', 403);
        }
    }

    /**
     * التحقق من صلاحية التعيين
     */
    private function checkAssignmentPermission(Client $client, User $user): void
    {
        $canAssign = false;

        // المدير العام والإداريين يمكنهم التعيين
        if ($user->is_admin) {
            $canAssign = true;
        }
        // مدير الوسيط يمكنه التعيين
        elseif ($client->assigned_agent_id && $client->assignedAgent->manager_id === $user->id) {
            $canAssign = true;
        }

        if (!$canAssign) {
            throw new BusinessException('غير مصرح لك بتعيين وسيط لهذا العميل', 403);
        }
    }
}
