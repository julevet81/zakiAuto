<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\StoreSettingRequest;
use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    /**
     * Settings are a small, flat key-value table — no pagination needed,
     * just return everything the user is allowed to see.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return response()->json([
            'data' => SettingResource::collection(Setting::orderBy('key')->get()),
        ]);
    }

    public function store(StoreSettingRequest $request): JsonResponse
    {
        $setting = Setting::create($request->validated());

        return response()->json([
            'message' => 'تم إضافة الإعداد بنجاح',
            'data' => new SettingResource($setting),
        ], 201);
    }

    public function show(Setting $setting): JsonResponse
    {
        $this->authorize('view', $setting);

        return response()->json(['data' => new SettingResource($setting)]);
    }

    /**
     * Update a setting's value by its route-bound record. The `key` is
     * intentionally immutable after creation — only `value` may change,
     * since other parts of the system may reference settings by key
     * (e.g. Setting::getValue('company_name')).
     */
    public function update(UpdateSettingRequest $request, Setting $setting): JsonResponse
    {
        $setting->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث الإعداد بنجاح',
            'data' => new SettingResource($setting),
        ]);
    }

    public function destroy(Setting $setting): JsonResponse
    {
        $this->authorize('delete', $setting);

        $setting->delete();

        return response()->json(['message' => 'تم حذف الإعداد بنجاح']);
    }
}
