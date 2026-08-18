<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;

class DepartmentPreference
{
    public const COOKIE = 'orbitra_department';

    /** @param array<string, mixed> $profile */
    public function remember(array $profile): ?HttpCookie
    {
        $department = $this->normalize($profile);

        return $department
            ? Cookie::make(self::COOKIE, json_encode($department, JSON_THROW_ON_ERROR), 60 * 24 * 30, '/', null, config('session.secure'), true, false, 'lax')
            : null;
    }

    /** @return array{id: int, code: string, name: string}|null */
    public function from(Request $request): ?array
    {
        $value = json_decode((string) $request->cookie(self::COOKIE), true);

        return is_array($value) ? $this->normalize($value) : null;
    }

    /** @param array<string, mixed> $value
     * @return array{id: int, code: string, name: string}|null
     */
    public function normalize(array $value): ?array
    {
        $id = (int) ($value['department_id'] ?? $value['id'] ?? 0);
        $code = strtoupper(trim((string) ($value['department_code'] ?? $value['code'] ?? '')));
        $name = trim((string) ($value['department_name'] ?? $value['name'] ?? ''));

        if ($id < 1 || $code === '') {
            return null;
        }

        return ['id' => $id, 'code' => $code, 'name' => $name ?: $code];
    }
}
