<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Exceptions\BusinessException;

abstract class BaseService
{
    /**
     * Model class للخدمة
     */
    protected $model;

    /**
     * البحث عن سجل
     */
    public function find($id, array $with = [])
    {
        return $this->model::with($with)->find($id);
    }

    /**
     * البحث عن سجل أو رمي استثناء
     */
    public function findOrFail($id, array $with = [])
    {
        $record = $this->find($id, $with);
        
        if (!$record) {
            throw new BusinessException(
                __('models.not_found', ['model' => class_basename($this->model)]),
                404
            );
        }

        return $record;
    }

    /**
     * البحث عن سجل باستخدام UUID
     */
    public function findByUuid(string $uuid, array $with = [])
    {
        return $this->model::with($with)->where('uuid', $uuid)->first();
    }

    /**
     * البحث عن سجل باستخدام UUID أو رمي استثناء
     */
    public function findByUuidOrFail(string $uuid, array $with = [])
    {
        $record = $this->findByUuid($uuid, $with);
        
        if (!$record) {
            throw new BusinessException(
                __('models.not_found', ['model' => class_basename($this->model)]),
                404
            );
        }

        return $record;
    }

    /**
     * الحصول على جميع السجلات
     */
    public function all(array $with = [], array $filters = [])
    {
        $query = $this->model::with($with);
        
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $query->where($field, $value);
            }
        }

        return $query->get();
    }

    /**
     * الحصول على سجلات مع التصفية والترتيب
     */
    public function paginate(
        int $perPage = 15,
        array $with = [],
        array $filters = [],
        array $sort = ['created_at', 'desc']
    ) {
        $query = $this->model::with($with);
        
        // تطبيق الفلاتر
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $query->where($field, $value);
            }
        }

        // تطبيق الترتيب
        if (!empty($sort) && count($sort) === 2) {
            $query->orderBy($sort[0], $sort[1]);
        }

        return $query->paginate($perPage);
    }

    /**
     * إنشاء سجل جديد
     */
    public function create(array $data)
    {
        try {
            DB::beginTransaction();
            
            $record = $this->model::create($data);
            
            DB::commit();
            
            return $record;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException(
                __('models.create_failed', ['model' => class_basename($this->model)]),
                500
            );
        }
    }

    /**
     * تحديث سجل
     */
    public function update($id, array $data)
    {
        try {
            DB::beginTransaction();
            
            $record = $this->findOrFail($id);
            $record->update($data);
            
            DB::commit();
            
            return $record->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException(
                __('models.update_failed', ['model' => class_basename($this->model)]),
                500
            );
        }
    }

    /**
     * تحديث سجل باستخدام UUID
     */
    public function updateByUuid(string $uuid, array $data)
    {
        try {
            DB::beginTransaction();
            
            $record = $this->findByUuidOrFail($uuid);
            $record->update($data);
            
            DB::commit();
            
            return $record->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException(
                __('models.update_failed', ['model' => class_basename($this->model)]),
                500
            );
        }
    }

    /**
     * حذف سجل
     */
    public function delete($id)
    {
        try {
            DB::beginTransaction();
            
            $record = $this->findOrFail($id);
            $deleted = $record->delete();
            
            DB::commit();
            
            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException(
                __('models.delete_failed', ['model' => class_basename($this->model)]),
                500
            );
        }
    }

    /**
     * حذف سجل باستخدام UUID
     */
    public function deleteByUuid(string $uuid)
    {
        try {
            DB::beginTransaction();
            
            $record = $this->findByUuidOrFail($uuid);
            $deleted = $record->delete();
            
            DB::commit();
            
            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException(
                __('models.delete_failed', ['model' => class_basename($this->model)]),
                500
            );
        }
    }

    /**
     * استعادة سجل محذوف
     */
    public function restore($id)
    {
        try {
            DB::beginTransaction();
            
            $record = $this->model::withTrashed()->findOrFail($id);
            $restored = $record->restore();
            
            DB::commit();
            
            return $restored;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException(
                __('models.restore_failed', ['model' => class_basename($this->model)]),
                500
            );
        }
    }

    /**
     * البحث المتقدم
     */
    public function search(
        string $search,
        array $searchableFields = [],
        array $with = [],
        array $filters = []
    ) {
        $query = $this->model::with($with);
        
        // تطبيق البحث
        if (!empty($search) && !empty($searchableFields)) {
            $query->where(function ($q) use ($search, $searchableFields) {
                foreach ($searchableFields as $field) {
                    $q->orWhere($field, 'ILIKE', "%{$search}%");
                }
            });
        }
        
        // تطبيق الفلاتر
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $query->where($field, $value);
            }
        }
        
        return $query->get();
    }

    /**
     * الحصول على الإحصائيات
     */
    public function getStats(): array
    {
        return [
            'total' => $this->model::count(),
            'active' => $this->model::where('status', 'active')->count(),
            'inactive' => $this->model::where('status', 'inactive')->count(),
            'deleted' => $this->model::onlyTrashed()->count(),
        ];
    }

    /**
     * التحقق من وجود سجل
     */
    public function exists(array $conditions): bool
    {
        return $this->model::where($conditions)->exists();
    }

    /**
     * الحصول على عدد السجلات
     */
    public function count(array $conditions = []): int
    {
        $query = $this->model::query();
        
        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }
        
        return $query->count();
    }

    /**
     * الحصول على السجلات الأخيرة
     */
    public function getLatest(int $limit = 10, array $with = [])
    {
        return $this->model::with($with)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * تنفيذ عملية ضمن transaction
     */
    protected function transaction(callable $callback)
    {
        try {
            DB::beginTransaction();
            
            $result = $callback();
            
            DB::commit();
            
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
