<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\PropertyService;
use App\Enums\PropertyStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PropertyController extends BaseController
{
    protected $propertyService;

    public function __construct(PropertyService $propertyService)
    {
        $this->propertyService = $propertyService;
    }

    /**
     * الحصول على قائمة العقارات
     */
    public function index(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_properties');
            
            $perPage = $request->input('per_page', 15);
            $filters = $request->only([
                'type', 'purpose', 'status', 'city', 'district', 'neighborhood',
                'min_price', 'max_price', 'min_area', 'max_area',
                'min_bedrooms', 'max_bedrooms', 'min_bathrooms',
                'is_featured', 'is_verified', 'search',
                'sort_by', 'sort_dir'
            ]);
            
            $properties = $this->propertyService->advancedSearch($filters, $perPage);
            
            return $this->paginate($properties);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على عقار محدد
     */
    public function show($uuid)
    {
        try {
            $property = $this->propertyService->findByUuidOrFail($uuid, [
                'owner', 'assignedAgent', 'creator'
            ]);
            
            // زيادة عداد المشاهدات
            $this->propertyService->incrementViewCount($uuid);
            
            return $this->success([
                'property' => array_merge($property->toArray(), [
                    'formatted_price' => $property->formatted_price,
                    'is_available' => $property->is_available,
                    'primary_image' => $property->primary_image,
                    'total_area' => $property->total_area,
                    'type_label' => $property->type_label,
                    'status_label' => $property->status_label,
                    'purpose_label' => $property->purpose_label,
                    'price_per_meter_calculated' => $property->price_per_meter_calculated,
                    'formatted_price_per_meter' => $property->formatted_price_per_meter,
                    'owner' => $property->owner ? [
                        'uuid' => $property->owner->uuid,
                        'full_name' => $property->owner->full_name,
                        'phone' => $property->owner->phone,
                    ] : null,
                    'assigned_agent' => $property->assignedAgent ? [
                        'uuid' => $property->assignedAgent->uuid,
                        'full_name' => $property->assignedAgent->full_name,
                        'phone' => $property->assignedAgent->phone,
                        'commission_rate' => $property->assignedAgent->commission_rate,
                    ] : null,
                ]),
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * إنشاء عقار جديد
     */
    public function store(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('create_properties');
            
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:200',
                'description' => 'nullable|string',
                'type' => 'required|in:' . implode(',', \App\Enums\PropertyType::values()),
                'purpose' => 'required|in:sale,rent,both',
                'address' => 'required|string',
                'city' => 'required|string|max:100',
                'district' => 'nullable|string|max:100',
                'neighborhood' => 'nullable|string|max:100',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'bedrooms' => 'nullable|integer|min:0',
                'bathrooms' => 'nullable|integer|min:0',
                'living_rooms' => 'nullable|integer|min:0',
                'kitchens' => 'nullable|integer|min:0',
                'built_area' => 'nullable|numeric|min:0',
                'land_area' => 'nullable|numeric|min:0',
                'floor' => 'nullable|integer',
                'total_floors' => 'nullable|integer',
                'year_built' => 'nullable|integer|min:1900|max:' . date('Y'),
                'furnishing' => 'nullable|in:furnished,semi_furnished,unfurnished',
                'price_egp' => 'required|numeric|min:0',
                'price_usd' => 'nullable|numeric|min:0',
                'price_type' => 'required|in:fixed,negotiable,by_meter',
                'price_per_meter' => 'nullable|numeric|min:0',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'owner_id' => 'nullable|exists:users,id',
                'assigned_agent_id' => 'nullable|exists:users,id',
                'images' => 'nullable|array',
                'images.*' => 'string',
                'features' => 'nullable|array',
                'features.*' => 'string',
                'amenities' => 'nullable|array',
                'amenities.*' => 'string',
                'has_mortgage' => 'boolean',
                'mortgage_details' => 'nullable|string',
                'has_maintenance' => 'boolean',
                'maintenance_fee' => 'nullable|numeric|min:0',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            // إنشاء العقار
            $creator = auth()->user();
            $property = $this->propertyService->createProperty($request->all(), $creator);
            
            return $this->success([
                'property' => array_merge($property->toArray(), [
                    'formatted_price' => $property->formatted_price,
                    'type_label' => $property->type_label,
                    'status_label' => $property->status_label,
                    'purpose_label' => $property->purpose_label,
                ]),
            ], 'تم إنشاء العقار بنجاح', 201);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * تحديث بيانات عقار
     */
    public function update(Request $request, $uuid)
    {
        try {
            // التحقق من الصلاحيات
            $updater = auth()->user();
            
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:200',
                'description' => 'sometimes|nullable|string',
                'type' => 'sometimes|in:' . implode(',', \App\Enums\PropertyType::values()),
                'purpose' => 'sometimes|in:sale,rent,both',
                'address' => 'sometimes|string',
                'city' => 'sometimes|string|max:100',
                'district' => 'sometimes|nullable|string|max:100',
                'neighborhood' => 'sometimes|nullable|string|max:100',
                'latitude' => 'sometimes|nullable|numeric|between:-90,90',
                'longitude' => 'sometimes|nullable|numeric|between:-180,180',
                'bedrooms' => 'sometimes|nullable|integer|min:0',
                'bathrooms' => 'sometimes|nullable|integer|min:0',
                'living_rooms' => 'sometimes|nullable|integer|min:0',
                'kitchens' => 'sometimes|nullable|integer|min:0',
                'built_area' => 'sometimes|nullable|numeric|min:0',
                'land_area' => 'sometimes|nullable|numeric|min:0',
                'floor' => 'sometimes|nullable|integer',
                'total_floors' => 'sometimes|nullable|integer',
                'year_built' => 'sometimes|nullable|integer|min:1900|max:' . date('Y'),
                'furnishing' => 'sometimes|nullable|in:furnished,semi_furnished,unfurnished',
                'price_egp' => 'sometimes|numeric|min:0',
                'price_usd' => 'sometimes|nullable|numeric|min:0',
                'price_type' => 'sometimes|in:fixed,negotiable,by_meter',
                'price_per_meter' => 'sometimes|nullable|numeric|min:0',
                'commission_rate' => 'sometimes|nullable|numeric|min:0|max:100',
                'owner_id' => 'sometimes|nullable|exists:users,id',
                'assigned_agent_id' => 'sometimes|nullable|exists:users,id',
                'images' => 'sometimes|nullable|array',
                'images.*' => 'string',
                'features' => 'sometimes|nullable|array',
                'features.*' => 'string',
                'amenities' => 'sometimes|nullable|array',
                'amenities.*' => 'string',
                'has_mortgage' => 'sometimes|boolean',
                'mortgage_details' => 'sometimes|nullable|string',
                'has_maintenance' => 'sometimes|boolean',
                'maintenance_fee' => 'sometimes|nullable|numeric|min:0',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            // تحديث العقار
            $property = $this->propertyService->updateProperty($uuid, $request->all(), $updater);
            
            return $this->success([
                'property' => array_merge($property->toArray(), [
                    'formatted_price' => $property->formatted_price,
                    'type_label' => $property->type_label,
                    'status_label' => $property->status_label,
                    'purpose_label' => $property->purpose_label,
                ]),
            ], 'تم تحديث العقار بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * حذف عقار
     */
    public function destroy($uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('delete_properties');
            
            $property = $this->propertyService->findByUuidOrFail($uuid);
            
            // لا يمكن حذف العقارات المباعة أو المؤجرة
            if (in_array($property->status, [PropertyStatus::SOLD->value, PropertyStatus::RENTED->value])) {
                return $this->error('لا يمكن حذف عقار مباع أو مؤجر', 403);
            }
            
            $this->propertyService->deleteByUuid($uuid);
            
            return $this->success(null, 'تم حذف العقار بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * تغيير حالة العقار
     */
    public function updateStatus(Request $request, $uuid)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:' . implode(',', PropertyStatus::values()),
                'reason' => 'sometimes|nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $changer = auth()->user();
            $status = PropertyStatus::from($request->status);
            
            $property = $this->propertyService->changePropertyStatus($uuid, $status, $changer, $request->reason);
            
            return $this->success([
                'property' => [
                    'uuid' => $property->uuid,
                    'title' => $property->title,
                    'status' => $property->status,
                    'status_label' => $property->status_label,
                ],
            ], 'تم تغيير حالة العقار بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * تعيين وسيط للعقار
     */
    public function assignAgent(Request $request, $propertyUuid)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'agent_uuid' => 'required|exists:users,uuid',
                'reason' => 'sometimes|nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $assigner = auth()->user();
            $property = $this->propertyService->assignAgent($propertyUuid, $request->agent_uuid, $assigner, $request->reason);
            
            return $this->success([
                'property' => [
                    'uuid' => $property->uuid,
                    'title' => $property->title,
                    'assigned_agent' => $property->assignedAgent ? [
                        'uuid' => $property->assignedAgent->uuid,
                        'full_name' => $property->assignedAgent->full_name,
                    ] : null,
                ],
            ], 'تم تعيين الوسيط للعقار بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * التحقق من العقار
     */
    public function verify(Request $request, $uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('verify_properties');
            
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'notes' => 'sometimes|nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $verifier = auth()->user();
            $property = $this->propertyService->verifyProperty($uuid, $verifier, $request->notes);
            
            return $this->success([
                'property' => [
                    'uuid' => $property->uuid,
                    'title' => $property->title,
                    'is_verified' => $property->is_verified,
                    'verified_at' => $property->verified_at,
                ],
            ], 'تم التحقق من العقار بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * جعل العقار مميزاً
     */
    public function markAsFeatured(Request $request, $uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('feature_properties');
            
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'featured' => 'required|boolean',
                'reason' => 'sometimes|nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $marker = auth()->user();
            $property = $this->propertyService->markAsFeatured($uuid, $request->featured, $marker, $request->reason);
            
            return $this->success([
                'property' => [
                    'uuid' => $property->uuid,
                    'title' => $property->title,
                    'is_featured' => $property->is_featured,
                    'featured_at' => $property->featured_at,
                ],
            ], $request->featured ? 'تم جعل العقار مميزاً' : 'تم إلغاء تمييز العقار');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على العقارات المميزة
     */
    public function featured()
    {
        try {
            $properties = $this->propertyService->getFeaturedProperties();
            
            return $this->success([
                'properties' => $properties->map(function ($property) {
                    return array_merge($property->only([
                        'uuid', 'title', 'type', 'purpose', 'status',
                        'city', 'district', 'price_egp', 'bedrooms',
                        'bathrooms', 'built_area', 'images', 'is_verified',
                        'created_at'
                    ]), [
                        'formatted_price' => $property->formatted_price,
                        'primary_image' => $property->primary_image,
                        'type_label' => $property->type_label,
                    ]);
                }),
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على العقارات الجديدة
     */
    public function newProperties()
    {
        try {
            $properties = $this->propertyService->getNewProperties();
            
            return $this->success([
                'properties' => $properties->map(function ($property) {
                    return array_merge($property->only([
                        'uuid', 'title', 'type', 'purpose', 'status',
                        'city', 'district', 'price_egp', 'bedrooms',
                        'bathrooms', 'built_area', 'images', 'is_verified',
                        'created_at'
                    ]), [
                        'formatted_price' => $property->formatted_price,
                        'primary_image' => $property->primary_image,
                        'type_label' => $property->type_label,
                    ]);
                }),
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على إحصائيات العقارات
     */
    public function stats(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_properties');
            
            $agentUuid = $request->input('agent_uuid');
            $stats = $this->propertyService->getPropertyStats($agentUuid);
            
            return $this->success([
                'stats' => $stats,
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على العقارات المتشابهة
     */
    public function similar($uuid)
    {
        try {
            $property = $this->propertyService->findByUuidOrFail($uuid);
            $similarProperties = $this->propertyService->getSimilarProperties($property);
            
            return $this->success([
                'similar_properties' => $similarProperties->map(function ($similar) {
                    return array_merge($similar->only([
                        'uuid', 'title', 'type', 'purpose', 'status',
                        'city', 'district', 'price_egp', 'bedrooms',
                        'bathrooms', 'built_area', 'images',
                        'created_at'
                    ]), [
                        'formatted_price' => $similar->formatted_price,
                        'primary_image' => $similar->primary_image,
                        'type_label' => $similar->type_label,
                    ]);
                }),
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * زيادة عداد الاستفسارات
     */
    public function incrementInquiryCount($uuid)
    {
        try {
            $this->propertyService->incrementInquiryCount($uuid);
            
            return $this->success(null, 'تم زيادة عداد الاستفسارات');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * إضافة صورة للعقار
     */
    public function addImage(Request $request, $uuid)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'image_url' => 'required|string|url',
                'is_primary' => 'sometimes|boolean',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $property = $this->propertyService->addPropertyImage(
                $uuid,
                $request->image_url,
                $request->is_primary ?? false
            );
            
            return $this->success([
                'property' => [
                    'uuid' => $property->uuid,
                    'title' => $property->title,
                    'images' => $property->images,
                    'primary_image' => $property->primary_image,
                ],
            ], 'تم إضافة الصورة بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * إزالة صورة من العقار
     */
    public function removeImage(Request $request, $uuid)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'image_url' => 'required|string|url',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $property = $this->propertyService->removePropertyImage($uuid, $request->image_url);
            
            return $this->success([
                'property' => [
                    'uuid' => $property->uuid,
                    'title' => $property->title,
                    'images' => $property->images,
                    'primary_image' => $property->primary_image,
                ],
            ], 'تم إزالة الصورة بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على تقرير العقارات
     */
    public function report(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_reports');
            
            $filters = $request->only([
                'start_date', 'end_date', 'types', 'cities'
            ]);
            
            $report = $this->propertyService->getPropertiesReport($filters);
            
            return $this->success([
                'report' => $report,
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
