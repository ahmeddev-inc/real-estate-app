<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\ClientService;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\Priority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientController extends BaseController
{
    protected $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * الحصول على قائمة العملاء
     */
    public function index(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_clients');
            
            $perPage = $request->input('per_page', 15);
            $filters = $request->only([
                'type', 'status', 'priority', 'city',
                'assigned_agent_id', 'search',
                'min_budget', 'max_budget',
                'min_bedrooms', 'needs_follow_up',
                'days_since_last_contact',
                'sort_by', 'sort_dir'
            ]);
            
            $clients = $this->clientService->advancedSearch($filters, $perPage);
            
            return $this->paginate($clients);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على عميل محدد
     */
    public function show($uuid)
    {
        try {
            $client = $this->clientService->findByUuidOrFail($uuid, ['assignedAgent', 'creator']);
            
            return $this->success([
                'client' => array_merge($client->toArray(), [
                    'full_name' => $client->full_name,
                    'is_lead' => $client->is_lead,
                    'is_client' => $client->is_client,
                    'days_since_last_contact' => $client->days_since_last_contact,
                    'type_label' => $client->type_label,
                    'status_label' => $client->status_label,
                    'priority_label' => $client->priority_label,
                    'is_overdue_for_follow_up' => $client->is_overdue_for_follow_up,
                    'budget_range' => $client->budget_range,
                    'assigned_agent' => $client->assignedAgent ? [
                        'uuid' => $client->assignedAgent->uuid,
                        'full_name' => $client->assignedAgent->full_name,
                        'phone' => $client->assignedAgent->phone,
                    ] : null,
                ]),
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * إنشاء عميل جديد
     */
    public function store(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('create_clients');
            
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:50',
                'last_name' => 'required|string|max:50',
                'email' => 'sometimes|nullable|email|unique:clients',
                'phone' => 'required|string|max:20|unique:clients',
                'phone_2' => 'sometimes|nullable|string|max:20',
                'national_id' => 'sometimes|nullable|string|max:20',
                'type' => 'required|in:' . implode(',', ClientType::values()),
                'source' => 'sometimes|in:website,referral,walk_in,phone_call,social_media,advertisement,previous_client,other',
                'city' => 'required|string|max:100',
                'district' => 'sometimes|nullable|string|max:100',
                'address' => 'sometimes|nullable|string',
                'status' => 'sometimes|in:' . implode(',', ClientStatus::values()),
                'priority' => 'sometimes|in:' . implode(',', Priority::values()),
                'min_budget' => 'sometimes|nullable|numeric|min:0',
                'max_budget' => 'sometimes|nullable|numeric|min:0',
                'min_bedrooms' => 'sometimes|nullable|integer|min:0',
                'max_bedrooms' => 'sometimes|nullable|integer|min:0',
                'min_area' => 'sometimes|nullable|numeric|min:0',
                'max_area' => 'sometimes|nullable|numeric|min:0',
                'preferred_property_types' => 'sometimes|nullable|array',
                'preferred_locations' => 'sometimes|nullable|array',
                'financing_type' => 'sometimes|in:cash,mortgage,both',
                'assigned_agent_id' => 'sometimes|nullable|exists:users,id',
                'notes' => 'sometimes|nullable|string',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            // إنشاء العميل
            $creator = auth()->user();
            $client = $this->clientService->createClient($request->all(), $creator);
            
            return $this->success([
                'client' => array_merge($client->toArray(), [
                    'full_name' => $client->full_name,
                    'type_label' => $client->type_label,
                    'status_label' => $client->status_label,
                    'priority_label' => $client->priority_label,
                ]),
            ], 'تم إنشاء العميل بنجاح', 201);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * تحديث بيانات عميل
     */
    public function update(Request $request, $uuid)
    {
        try {
            // التحقق من الصلاحيات
            $updater = auth()->user();
            
            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:50',
                'last_name' => 'sometimes|string|max:50',
                'email' => 'sometimes|nullable|email',
                'phone' => 'sometimes|string|max:20',
                'phone_2' => 'sometimes|nullable|string|max:20',
                'national_id' => 'sometimes|nullable|string|max:20',
                'type' => 'sometimes|in:' . implode(',', ClientType::values()),
                'city' => 'sometimes|string|max:100',
                'district' => 'sometimes|nullable|string|max:100',
                'address' => 'sometimes|nullable|string',
                'status' => 'sometimes|in:' . implode(',', ClientStatus::values()),
                'priority' => 'sometimes|in:' . implode(',', Priority::values()),
                'min_budget' => 'sometimes|nullable|numeric|min:0',
                'max_budget' => 'sometimes|nullable|numeric|min:0',
                'min_bedrooms' => 'sometimes|nullable|integer|min:0',
                'max_bedrooms' => 'sometimes|nullable|integer|min:0',
                'min_area' => 'sometimes|nullable|numeric|min:0',
                'max_area' => 'sometimes|nullable|numeric|min:0',
                'preferred_property_types' => 'sometimes|nullable|array',
                'preferred_locations' => 'sometimes|nullable|array',
                'financing_type' => 'sometimes|in:cash,mortgage,both',
                'assigned_agent_id' => 'sometimes|nullable|exists:users,id',
                'notes' => 'sometimes|nullable|string',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            // تحديث العميل
            $client = $this->clientService->updateClient($uuid, $request->all(), $updater);
            
            return $this->success([
                'client' => array_merge($client->toArray(), [
                    'full_name' => $client->full_name,
                    'type_label' => $client->type_label,
                    'status_label' => $client->status_label,
                    'priority_label' => $client->priority_label,
                ]),
            ], 'تم تحديث العميل بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * حذف عميل
     */
    public function destroy($uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('delete_clients');
            
            $client = $this->clientService->findByUuidOrFail($uuid);
            
            // لا يمكن حذف العملاء النشطين
            if ($client->status === ClientStatus::CLIENT) {
                return $this->error('لا يمكن حذف عميل نشط', 403);
            }
            
            $this->clientService->deleteByUuid($uuid);
            
            return $this->success(null, 'تم حذف العميل بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * تغيير حالة العميل
     */
    public function updateStatus(Request $request, $uuid)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:' . implode(',', ClientStatus::values()),
                'reason' => 'sometimes|nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $changer = auth()->user();
            $status = ClientStatus::from($request->status);
            
            $client = $this->clientService->changeClientStatus($uuid, $status, $changer, $request->reason);
            
            return $this->success([
                'client' => [
                    'uuid' => $client->uuid,
                    'full_name' => $client->full_name,
                    'status' => $client->status,
                    'status_label' => $client->status_label,
                ],
            ], 'تم تغيير حالة العميل بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * تعيين وسيط للعميل
     */
    public function assignAgent(Request $request, $clientUuid)
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
            $client = $this->clientService->assignAgent($clientUuid, $request->agent_uuid, $assigner, $request->reason);
            
            return $this->success([
                'client' => [
                    'uuid' => $client->uuid,
                    'full_name' => $client->full_name,
                    'assigned_agent' => $client->assignedAgent ? [
                        'uuid' => $client->assignedAgent->uuid,
                        'full_name' => $client->assignedAgent->full_name,
                    ] : null,
                ],
            ], 'تم تعيين الوسيط للعميل بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * تحديث آخر اتصال مع العميل
     */
    public function updateLastContact(Request $request, $uuid)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'notes' => 'sometimes|nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $contactedBy = auth()->user();
            $client = $this->clientService->updateLastContact($uuid, $contactedBy, $request->notes);
            
            return $this->success([
                'client' => [
                    'uuid' => $client->uuid,
                    'full_name' => $client->full_name,
                    'last_contacted_at' => $client->last_contacted_at,
                    'next_follow_up_at' => $client->next_follow_up_at,
                ],
            ], 'تم تحديث آخر اتصال بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * جدولة متابعة للعميل
     */
    public function scheduleFollowUp(Request $request, $uuid)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'follow_up_date' => 'required|date|after:now',
                'reason' => 'sometimes|nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $scheduler = auth()->user();
            $client = $this->clientService->scheduleFollowUp(
                $uuid,
                new \DateTime($request->follow_up_date),
                $scheduler,
                $request->reason
            );
            
            return $this->success([
                'client' => [
                    'uuid' => $client->uuid,
                    'full_name' => $client->full_name,
                    'next_follow_up_at' => $client->next_follow_up_at,
                ],
            ], 'تم جدولة المتابعة بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على العملاء الذين يحتاجون متابعة
     */
    public function needsFollowUp(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_clients');
            
            $perPage = $request->input('per_page', 15);
            $agentUuid = $request->input('agent_uuid');
            
            $clients = $this->clientService->getClientsNeedingFollowUp($agentUuid, $perPage);
            
            return $this->paginate($clients);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على العملاء الجدد
     */
    public function newClients(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_clients');
            
            $days = $request->input('days', 7);
            $agentUuid = $request->input('agent_uuid');
            
            $clients = $this->clientService->getNewClients($days, $agentUuid);
            
            return $this->success([
                'clients' => $clients->map(function ($client) {
                    return array_merge($client->only([
                        'uuid', 'first_name', 'last_name', 'type', 'status',
                        'city', 'phone', 'email', 'priority', 'source',
                        'created_at'
                    ]), [
                        'full_name' => $client->full_name,
                        'type_label' => $client->type_label,
                        'status_label' => $client->status_label,
                        'priority_label' => $client->priority_label,
                    ]);
                }),
                'count' => $clients->count(),
                'period_days' => $days,
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على إحصائيات العملاء
     */
    public function stats(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_clients');
            
            $agentUuid = $request->input('agent_uuid');
            $stats = $this->clientService->getClientStats($agentUuid);
            
            return $this->success([
                'stats' => $stats,
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * مطابقة العميل مع العقارات المناسبة
     */
    public function matchProperties($uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_clients');
            
            $limit = request()->input('limit', 10);
            $matchedProperties = $this->clientService->matchClientWithProperties($uuid, $limit);
            
            return $this->success([
                'matches' => $matchedProperties->map(function ($match) {
                    return [
                        'property' => array_merge($match['property']->only([
                            'uuid', 'title', 'type', 'purpose', 'status',
                            'city', 'district', 'price_egp', 'bedrooms',
                            'bathrooms', 'built_area', 'images',
                            'address', 'created_at'
                        ]), [
                            'formatted_price' => $match['property']->formatted_price,
                            'primary_image' => $match['property']->primary_image,
                            'type_label' => $match['property']->type_label,
                        ]),
                        'match_score' => round($match['match_score'], 2),
                        'match_details' => $match['match_details'],
                    ];
                }),
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * إضافة ملاحظة للعميل
     */
    public function addNote(Request $request, $uuid)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'note' => 'required|string|max:1000',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $author = auth()->user();
            $client = $this->clientService->addNote($uuid, $request->note, $author);
            
            return $this->success([
                'client' => [
                    'uuid' => $client->uuid,
                    'full_name' => $client->full_name,
                    'notes_preview' => substr($client->notes, 0, 200) . (strlen($client->notes) > 200 ? '...' : ''),
                ],
            ], 'تم إضافة الملاحظة بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * إضافة علامة للعميل
     */
    public function addTag(Request $request, $uuid)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'tag' => 'required|string|max:50',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $addedBy = auth()->user();
            $client = $this->clientService->addTag($uuid, $request->tag, $addedBy);
            
            return $this->success([
                'client' => [
                    'uuid' => $client->uuid,
                    'full_name' => $client->full_name,
                    'tags' => $client->tags,
                ],
            ], 'تم إضافة العلامة بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * إزالة علامة من العميل
     */
    public function removeTag(Request $request, $uuid)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'tag' => 'required|string|max:50',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $removedBy = auth()->user();
            $client = $this->clientService->removeTag($uuid, $request->tag, $removedBy);
            
            return $this->success([
                'client' => [
                    'uuid' => $client->uuid,
                    'full_name' => $client->full_name,
                    'tags' => $client->tags,
                ],
            ], 'تم إزالة العلامة بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على تقرير العملاء
     */
    public function report(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_reports');
            
            $filters = $request->only([
                'start_date', 'end_date', 'types', 'cities', 'agent_id'
            ]);
            
            $report = $this->clientService->getClientsReport($filters);
            
            return $this->success([
                'report' => $report,
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
