<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\PropertyPurpose;
use Illuminate\Support\Facades\DB;
use App\Exceptions\BusinessException;

class PropertyService extends BaseService
{
    public function __construct()
    {
        $this->model = Property::class;
    }

    /**
     * إنشاء عقار جديد
     */
    public function createProperty(array $data, User $creator): Property
    {
        return $this->transaction(function () use ($data, $creator) {
            // تعيين القيم الافتراضية
            $data['created_by'] = $creator->id;
            $data['status'] = $data['status'] ?? PropertyStatus::DRAFT->value;
            
            // التحقق من تعيين مالك أو وسيط
            if (empty($data['owner_id']) && empty($data['assigned_agent_id'])) {
                throw new BusinessException('يجب تعيين مالك أو وسيط للعقار', 400);
            }

            // التحقق من صلاحية المالك
            if (!empty($data['owner_id'])) {
                $owner = User::find($data['owner_id']);
                if (!$owner || $owner->role !== 'owner') {
                    throw new BusinessException('المستخدم المحدد ليس مالكاً', 400);
                }
            }

            // التحقق من صلاحية الوسيط
            if (!empty($data['assigned_agent_id'])) {
                $agent = User::find($data['assigned_agent_id']);
                if (!$agent || $agent->role !== 'agent') {
                    throw new BusinessException('المستخدم المحدد ليس وسيطاً', 400);
                }
            }

            // إنشاء العقار
            $property = Property::create($data);

            // تسجيل الحدث
            $property->logAudit('created', $property->toArray());

            return $property;
        });
    }

    /**
     * تحديث عقار
     */
    public function updateProperty(string $uuid, array $data, User $updater): Property
    {
        return $this->transaction(function () use ($uuid, $data, $updater) {
            $property = $this->findByUuidOrFail($uuid);

            // التحقق من صلاحية التعديل
            $this->checkUpdatePermission($property, $updater);

            // حفظ البيانات القديمة
            $oldData = $property->toArray();

            // تحديث العقار
            $property->update($data);

            // تسجيل التغييرات
            $property->logAudit('updated', $property->getChanges(), $oldData);

            return $property->fresh();
        });
    }

    /**
     * تغيير حالة العقار
     */
    public function changePropertyStatus(string $uuid, PropertyStatus $status, User $changer, ?string $reason = null): Property
    {
        return $this->transaction(function () use ($uuid, $status, $changer, $reason) {
            $property = $this->findByUuidOrFail($uuid);

            // التحقق من صلاحية تغيير الحالة
            $this->checkStatusChangePermission($property, $status, $changer);

            // تغيير الحالة
            $property->changeStatus($status, $reason);

            // تسجيل تغيير الحالة
            $property->logAudit('status_changed', [
                'new_status' => $status->value,
                'old_status' => $property->getOriginal('status'),
                'changed_by' => $changer->id,
                'reason' => $reason,
            ]);

            return $property->fresh();
        });
    }

    /**
     * تعيين وسيط للعقار
     */
    public function assignAgent(string $propertyUuid, string $agentUuid, User $assigner, ?string $reason = null): Property
    {
        return $this->transaction(function () use ($propertyUuid, $agentUuid, $assigner, $reason) {
            $property = $this->findByUuidOrFail($propertyUuid);
            $agent = User::where('uuid', $agentUuid)->firstOrFail();

            // التحقق من أن المستخدم وسيط
            if ($agent->role !== 'agent') {
                throw new BusinessException('المستخدم المحدد ليس وسيطاً', 400);
            }

            // التحقق من صلاحية التعيين
            $this->checkAssignmentPermission($property, $assigner);

            $oldAgentId = $property->assigned_agent_id;

            // تعيين الوسيط
            $property->assignAgent($agent->id, $reason);

            // تسجيل التعيين
            $property->logAudit('agent_assigned', [
                'new_agent_id' => $agent->id,
                'old_agent_id' => $oldAgentId,
                'assigned_by' => $assigner->id,
                'reason' => $reason,
            ]);

            return $property->fresh();
        });
    }

    /**
     * التحقق من العقار
     */
    public function verifyProperty(string $uuid, User $verifier, ?string $notes = null): Property
    {
        return $this->transaction(function () use ($uuid, $verifier, $notes) {
            $property = $this->findByUuidOrFail($uuid);

            // التحقق من صلاحية التحقق
            if (!$verifier->is_admin) {
                throw new BusinessException('غير مصرح لك بالتحقق من العقار', 403);
            }

            // التحقق من العقار
            $property->verify($verifier->id, $notes);

            // تسجيل التحقق
            $property->logAudit('verified', [
                'verified_by' => $verifier->id,
                'notes' => $notes,
            ]);

            return $property->fresh();
        });
    }

    /**
     * جعل العقار مميزاً
     */
    public function markAsFeatured(string $uuid, bool $featured, User $marker, ?string $reason = null): Property
    {
        return $this->transaction(function () use ($uuid, $featured, $marker, $reason) {
            $property = $this->findByUuidOrFail($uuid);

            // التحقق من صلاحية التميز
            if (!$marker->is_admin) {
                throw new BusinessException('غير مصرح لك بتحديد العقارات المميزة', 403);
            }

            // تحديث حالة التميز
            $property->markAsFeatured($featured, $reason);

            // تسجيل التغيير
            $property->logAudit($featured ? 'featured' : 'unfeatured', [
                'changed_by' => $marker->id,
                'reason' => $reason,
            ]);

            return $property->fresh();
        });
    }

    /**
     * البحث المتقدم في العقارات
     */
    public function advancedSearch(array $filters = [], int $perPage = 15)
    {
        $query = Property::query();

        // تطبيق الفلاتر الأساسية
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['city'])) {
            $query->where('city', 'ILIKE', "%{$filters['city']}%");
        }

        if (!empty($filters['district'])) {
            $query->where('district', 'ILIKE', "%{$filters['district']}%");
        }

        if (!empty($filters['neighborhood'])) {
            $query->where('neighborhood', 'ILIKE', "%{$filters['neighborhood']}%");
        }

        // تطبيق فلاتر السعر
        if (!empty($filters['min_price'])) {
            $query->where('price_egp', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('price_egp', '<=', $filters['max_price']);
        }

        // تطبيق فلاتر المساحة
        if (!empty($filters['min_area'])) {
            $query->where('built_area', '>=', $filters['min_area']);
        }

        if (!empty($filters['max_area'])) {
            $query->where('built_area', '<=', $filters['max_area']);
        }

        // تطبيق فلاتر الغرف
        if (!empty($filters['min_bedrooms'])) {
            $query->where('bedrooms', '>=', $filters['min_bedrooms']);
        }

        if (!empty($filters['max_bedrooms'])) {
            $query->where('bedrooms', '<=', $filters['max_bedrooms']);
        }

        if (!empty($filters['min_bathrooms'])) {
            $query->where('bathrooms', '>=', $filters['min_bathrooms']);
        }

        // تطبيق فلاتر الحالة
        if (!empty($filters['is_featured'])) {
            $query->where('is_featured', filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['is_verified'])) {
            $query->where('is_verified', filter_var($filters['is_verified'], FILTER_VALIDATE_BOOLEAN));
        }

        // تطبيق البحث النصي
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('description', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('address', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('code', 'ILIKE', "%{$filters['search']}%");
            });
        }

        // تطبيق الترتيب
        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * الحصول على العقارات المميزة
     */
    public function getFeaturedProperties(int $limit = 10)
    {
        return Property::where('is_featured', true)
            ->where('status', PropertyStatus::AVAILABLE->value)
            ->where('is_verified', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * الحصول على العقارات الجديدة
     */
    public function getNewProperties(int $limit = 10)
    {
        return Property::where('status', PropertyStatus::AVAILABLE->value)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * الحصول على إحصائيات العقارات
     */
    public function getPropertyStats(?string $agentUuid = null): array
    {
        $query = Property::query();

        if ($agentUuid) {
            $agent = User::where('uuid', $agentUuid)->firstOrFail();
            $query->where('assigned_agent_id', $agent->id);
        }

        $total = $query->count();
        $available = (clone $query)->where('status', PropertyStatus::AVAILABLE->value)->count();
        $sold = (clone $query)->where('status', PropertyStatus::SOLD->value)->count();
        $rented = (clone $query)->where('status', PropertyStatus::RENTED->value)->count();
        $reserved = (clone $query)->where('status', PropertyStatus::RESERVED->value)->count();
        $draft = (clone $query)->where('status', PropertyStatus::DRAFT->value)->count();

        // إحصائيات السعر
        $priceStats = $this->getPriceStats($query);

        return [
            'total' => $total,
            'available' => $available,
            'sold' => $sold,
            'rented' => $rented,
            'reserved' => $reserved,
            'draft' => $draft,
            'availability_rate' => $total > 0 ? ($available / $total) * 100 : 0,
            'success_rate' => $total > 0 ? (($sold + $rented) / $total) * 100 : 0,
            'price_stats' => $priceStats,
        ];
    }

    /**
     * الحصول على إحصائيات السعر
     */
    private function getPriceStats($query): array
    {
        $minPrice = (clone $query)->where('status', PropertyStatus::AVAILABLE->value)->min('price_egp');
        $maxPrice = (clone $query)->where('status', PropertyStatus::AVAILABLE->value)->max('price_egp');
        $avgPrice = (clone $query)->where('status', PropertyStatus::AVAILABLE->value)->avg('price_egp');

        return [
            'min' => $minPrice ?? 0,
            'max' => $maxPrice ?? 0,
            'avg' => $avgPrice ?? 0,
        ];
    }

    /**
     * زيادة عداد المشاهدات
     */
    public function incrementViewCount(string $uuid): void
    {
        $property = $this->findByUuidOrFail($uuid);
        $property->incrementViewCount();
    }

    /**
     * زيادة عداد الاستفسارات
     */
    public function incrementInquiryCount(string $uuid): void
    {
        $property = $this->findByUuidOrFail($uuid);
        $property->incrementInquiryCount();
    }

    /**
     * الحصول على العقارات المتشابهة
     */
    public function getSimilarProperties(Property $property, int $limit = 5)
    {
        return Property::where('id', '!=', $property->id)
            ->where('type', $property->type)
            ->where('city', $property->city)
            ->where('status', PropertyStatus::AVAILABLE->value)
            ->whereBetween('price_egp', [
                $property->price_egp * 0.7,
                $property->price_egp * 1.3
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * إضافة صورة للعقار
     */
    public function addPropertyImage(string $uuid, string $imageUrl, bool $isPrimary = false): Property
    {
        return $this->transaction(function () use ($uuid, $imageUrl, $isPrimary) {
            $property = $this->findByUuidOrFail($uuid);
            
            $property->addImage($imageUrl, $isPrimary);
            
            // تسجيل إضافة الصورة
            $property->logAudit('image_added', [
                'image_url' => $imageUrl,
                'is_primary' => $isPrimary,
            ]);

            return $property->fresh();
        });
    }

    /**
     * إزالة صورة من العقار
     */
    public function removePropertyImage(string $uuid, string $imageUrl): Property
    {
        return $this->transaction(function () use ($uuid, $imageUrl) {
            $property = $this->findByUuidOrFail($uuid);
            
            $images = $property->images ?? [];
            $index = array_search($imageUrl, $images);
            
            if ($index !== false) {
                unset($images[$index]);
                $property->update(['images' => array_values($images)]);
                
                // تسجيل إزالة الصورة
                $property->logAudit('image_removed', [
                    'image_url' => $imageUrl,
                ]);
            }

            return $property->fresh();
        });
    }

    /**
     * التحقق من صلاحية التعديل
     */
    private function checkUpdatePermission(Property $property, User $user): void
    {
        $canEdit = false;

        // المدير العام والإداريين يمكنهم تعديل كل شيء
        if ($user->is_admin) {
            $canEdit = true;
        }
        // الوسيط المسؤول يمكنه التعديل
        elseif ($property->assigned_agent_id === $user->id) {
            $canEdit = true;
        }
        // المالك يمكنه التعديل
        elseif ($property->owner_id === $user->id) {
            $canEdit = true;
        }
        // منشئ السجل يمكنه التعديل (إذا كان مسودة)
        elseif ($property->created_by === $user->id && $property->status === PropertyStatus::DRAFT) {
            $canEdit = true;
        }

        if (!$canEdit) {
            throw new BusinessException('غير مصرح لك بتعديل هذا العقار', 403);
        }
    }

    /**
     * التحقق من صلاحية تغيير الحالة
     */
    private function checkStatusChangePermission(Property $property, PropertyStatus $newStatus, User $user): void
    {
        $canChange = false;

        // المدير العام والإداريين يمكنهم تغيير كل الحالات
        if ($user->is_admin) {
            $canChange = true;
        }
        // الوسيط المسؤول يمكنه تغيير الحالة إلى متاح/محجوز
        elseif ($property->assigned_agent_id === $user->id) {
            if (in_array($newStatus, [PropertyStatus::AVAILABLE, PropertyStatus::RESERVED])) {
                $canChange = true;
            }
        }
        // منشئ السجل يمكنه تغيير المسودة إلى متاح
        elseif ($property->created_by === $user->id) {
            if ($property->status === PropertyStatus::DRAFT && $newStatus === PropertyStatus::AVAILABLE) {
                $canChange = true;
            }
        }

        if (!$canChange) {
            throw new BusinessException('غير مصرح لك بتغيير حالة هذا العقار', 403);
        }
    }

    /**
     * التحقق من صلاحية التعيين
     */
    private function checkAssignmentPermission(Property $property, User $user): void
    {
        $canAssign = false;

        // المدير العام والإداريين يمكنهم التعيين
        if ($user->is_admin) {
            $canAssign = true;
        }
        // مدير الوسيط يمكنه التعيين
        elseif ($property->assigned_agent_id && $property->assignedAgent->manager_id === $user->id) {
            $canAssign = true;
        }

        if (!$canAssign) {
            throw new BusinessException('غير مصرح لك بتعيين وسيط لهذا العقار', 403);
        }
    }

    /**
     * الحصول على العقارات التي تحتاج متابعة
     */
    public function getPropertiesNeedingAttention(int $daysThreshold = 7)
    {
        $thresholdDate = now()->subDays($daysThreshold);

        return Property::where('status', PropertyStatus::AVAILABLE->value)
            ->where('created_at', '<=', $thresholdDate)
            ->where(function ($query) use ($thresholdDate) {
                $query->where('last_updated_at', '<=', $thresholdDate)
                      ->orWhereNull('last_updated_at');
            })
            ->get();
    }

    /**
     * الحصول على تقرير العقارات
     */
    public function getPropertiesReport(array $filters = []): array
    {
        $query = Property::query();

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

        // تجميع البيانات
        $totalProperties = $query->count();
        $totalValue = $query->sum('price_egp');
        $averagePrice = $query->avg('price_egp');

        // تجميع حسب النوع
        $byType = (clone $query)
            ->selectRaw('type, COUNT(*) as count, SUM(price_egp) as total_value, AVG(price_egp) as avg_price')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->type => [
                    'count' => $item->count,
                    'total_value' => $item->total_value,
                    'avg_price' => $item->avg_price,
                ]];
            })
            ->toArray();

        // تجميع حسب المدينة
        $byCity = (clone $query)
            ->selectRaw('city, COUNT(*) as count, SUM(price_egp) as total_value, AVG(price_egp) as avg_price')
            ->groupBy('city')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->city => [
                    'count' => $item->count,
                    'total_value' => $item->total_value,
                    'avg_price' => $item->avg_price,
                ]];
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

        return [
            'summary' => [
                'total_properties' => $totalProperties,
                'total_value' => $totalValue,
                'average_price' => $averagePrice,
                'period' => [
                    'start_date' => $filters['start_date'] ?? null,
                    'end_date' => $filters['end_date'] ?? null,
                ],
            ],
            'by_type' => $byType,
            'by_city' => $byCity,
            'by_status' => $byStatus,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
