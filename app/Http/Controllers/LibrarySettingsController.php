<?php

namespace App\Http\Controllers;

use App\Http\Requests\LegacyEndpointRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LibrarySettingsController extends Controller
{
    private const ALLOWED_SETTINGS = ['library_email', 'library_phone', 'library_hours', 'library_location'];

    public function index(): JsonResponse
    {
        $settings = DB::connection('preregistration')->table('Library_Settings')
            ->orderBy('setting_id')->pluck('setting_value', 'setting_key');
        return ApiResponse::success(['settings' => $settings]);
    }

    public function update(LegacyEndpointRequest $request): JsonResponse
    {
        $settings = array_intersect_key($request->input('settings', []), array_flip(self::ALLOWED_SETTINGS));
        $connection = DB::connection('preregistration');
        $connection->transaction(function () use ($connection, $settings): void {
            foreach ($settings as $key => $value) {
                $connection->table('Library_Settings')->updateOrInsert(
                    ['setting_key' => $key],
                    ['setting_value' => (string) $value],
                );
            }
        });

        return ApiResponse::success([], 200, 'Successfully updated ' . count($settings) . ' settings');
    }
}