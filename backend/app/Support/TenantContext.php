<?php

namespace App\Support;

/**
 * TenantContext — menyimpan pdam_org_id aktif untuk request berjalan.
 * Di-set oleh middleware SetTenant dari user terautentikasi.
 */
class TenantContext
{
    protected static ?int $orgId = null;

    public static function set(?int $orgId): void
    {
        static::$orgId = $orgId;
    }

    public static function id(): ?int
    {
        return static::$orgId;
    }

    public static function clear(): void
    {
        static::$orgId = null;
    }
}
