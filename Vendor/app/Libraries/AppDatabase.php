<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Single entry for application DB access: always resolves through {@see \Config\Database}.
 * Use {@see self::connection()} instead of ad‑hoc drivers or raw credentials after install.
 */
final class AppDatabase
{
    /**
     * Default connection group from Config\Database.
     *
     * @see db_connect()
     */
    public static function connection(bool $getShared = true): BaseConnection
    {
        return db_connect(null, $getShared);
    }
}
