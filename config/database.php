<?php
/**
 * RideFlow — Database Connection (PDO Singleton)
 * Usage:  $pdo = db();
 */
require_once __DIR__.'/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
        if ($driver === 'pgsql') {
            $dbHost = DB_HOST;
            $dbPort = defined('DB_PORT') ? DB_PORT : 5432;
            $dbName = DB_NAME;
            $dbUser = DB_USER;
            $dbPass = DB_PASS;
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require', $dbHost, $dbPort, $dbName);
        } else {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                defined('DB_CHAR') ? DB_CHAR : 'utf8mb4'
            );
            $dbUser = DB_USER;
            $dbPass = DB_PASS;
        }
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        if (APP_ENV === 'development') {
            die('<pre style="color:red;padding:20px">DB Connection Failed: '.$e->getMessage().'</pre>');
        }
        die('Service temporarily unavailable. Please try again later.');
    }

    static $migrated = false;
    if ((defined('DB_DRIVER') ? DB_DRIVER : 'mysql') !== 'mysql') {
        return $pdo;
    }
    if (!$migrated) {
        $migrated = true;
        try {
            $cols = [];
            foreach ($pdo->query('DESCRIBE users') as $row) {
                $cols[] = $row['Field'];
            }
            $pending = [];
            if (!in_array('id_type', $cols, true)) {
                $pending[] = "ADD COLUMN id_type ENUM('NIC','Passport','Driver License','Other') NULL";
            }
            if (!in_array('id_number', $cols, true)) {
                $pending[] = "ADD COLUMN id_number VARCHAR(120) NULL";
            }
            if (!in_array('selfie_url', $cols, true)) {
                $pending[] = "ADD COLUMN selfie_url VARCHAR(500) NULL";
            }
            if (!in_array('id_document_url', $cols, true)) {
                $pending[] = "ADD COLUMN id_document_url VARCHAR(500) NULL";
            }
            if (!in_array('verification_status', $cols, true)) {
                $pending[] = "ADD COLUMN verification_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'";
            }
            if (!in_array('verification_note', $cols, true)) {
                $pending[] = "ADD COLUMN verification_note TEXT NULL";
            }
            if (!in_array('password_hash', $cols, true)) {
                $pending[] = "ADD COLUMN password_hash VARCHAR(255) NULL";
            }
            if (!in_array('social_provider', $cols, true)) {
                $pending[] = "ADD COLUMN social_provider VARCHAR(40) NULL";
            }
            if ($pending) {
                $pdo->exec('ALTER TABLE users ' . implode(', ', $pending));
            }
            if (in_array('password', $cols, true)) {
                $pdo->exec("UPDATE users SET password_hash=password WHERE (password_hash IS NULL OR password_hash='') AND password<>''");
            }

            if (db_table_exists('bookings')) {
                $bookingCols = [];
                foreach ($pdo->query('DESCRIBE bookings') as $row) {
                    $bookingCols[] = $row['Field'];
                }
                if (!in_array('created_at', $bookingCols, true)) {
                    $pdo->exec('ALTER TABLE bookings ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
                    if (in_array('booked_at', $bookingCols, true)) {
                        $pdo->exec('UPDATE bookings SET created_at=booked_at');
                    }
                }
            }

            // Older installations may have been created before vehicle
            // availability was tracked. Keep them compatible with the
            // current admin and schedule queries.
            if (db_table_exists('vehicles')) {
                $vehicleCols = [];
                foreach ($pdo->query('DESCRIBE vehicles') as $row) {
                    $vehicleCols[] = $row['Field'];
                }
                if (!in_array('type_id', $vehicleCols, true)) {
                    $pdo->exec('ALTER TABLE vehicles ADD COLUMN type_id INT UNSIGNED NULL');
                    $defaultType = $pdo->query('SELECT id FROM transport_types ORDER BY id LIMIT 1')->fetchColumn();
                    if ($defaultType !== false) {
                        $stmt = $pdo->prepare('UPDATE vehicles SET type_id=? WHERE type_id IS NULL');
                        $stmt->execute([(int)$defaultType]);
                    }
                }
                if (!in_array('vehicle_number', $vehicleCols, true)) {
                    $pdo->exec('ALTER TABLE vehicles ADD COLUMN vehicle_number VARCHAR(30) NULL');
                    foreach (['plate_no', 'plate_number', 'registration_number'] as $legacyNumberColumn) {
                        if (in_array($legacyNumberColumn, $vehicleCols, true)) {
                            $pdo->exec("UPDATE vehicles SET vehicle_number=`$legacyNumberColumn` WHERE vehicle_number IS NULL");
                            break;
                        }
                    }
                }
                if (!in_array('status', $vehicleCols, true)) {
                    $pdo->exec("ALTER TABLE vehicles ADD COLUMN status ENUM('active','maintenance','retired') NOT NULL DEFAULT 'active'");
                }
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS drivers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                vehicle_id INT UNSIGNED NULL,
                license_no VARCHAR(50) NOT NULL,
                experience INT NOT NULL DEFAULT 0,
                rating DECIMAL(3,2) NOT NULL DEFAULT 5.00,
                status ENUM('active','inactive','on_trip') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_user (user_id),
                INDEX idx_vehicle (vehicle_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            if (db_table_exists('feedback')) {
                $feedbackCols = [];
                foreach ($pdo->query('DESCRIBE feedback') as $row) {
                    $feedbackCols[] = $row['Field'];
                }
                if (!in_array('booking_id', $feedbackCols, true)) {
                    $pdo->exec('ALTER TABLE feedback ADD COLUMN booking_id INT NULL AFTER user_id');
                    $pdo->exec('ALTER TABLE feedback ADD INDEX idx_feedback_booking (booking_id)');
                }
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NOT NULL,
                user_id INT NOT NULL,
                gateway VARCHAR(40) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'LKR',
                status ENUM('pending','completed','refunded','failed') NOT NULL DEFAULT 'pending',
                transaction_ref VARCHAR(100) NULL,
                paid_at DATETIME NULL,
                payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_payment_booking (booking_id),
                INDEX idx_payment_user (user_id),
                INDEX idx_payment_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(180) NOT NULL UNIQUE,
                name VARCHAR(120) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
                setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
                setting_value TEXT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS chatbot_faq (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                question TEXT NOT NULL,
                answer TEXT NOT NULL,
                keywords VARCHAR(500) NULL,
                category VARCHAR(60) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS chatbot_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                session_id VARCHAR(64) NOT NULL,
                role ENUM('user','assistant') NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_session (session_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Keep the remaining application features available on older
            // databases that were created before the full schema was imported.
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('booking','payment','system','promo') NOT NULL DEFAULT 'system',
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                link VARCHAR(300) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_unread (user_id, is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(180) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_read (is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS api_tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                last_used DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS otp_codes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                phone VARCHAR(20) NOT NULL,
                code VARCHAR(10) NOT NULL,
                purpose VARCHAR(30) NOT NULL DEFAULT 'login',
                expires_at DATETIME NOT NULL,
                used TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_phone (phone),
                INDEX idx_purpose (phone, purpose)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS route_stops (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                route_id INT UNSIGNED NOT NULL,
                stop_name VARCHAR(100) NOT NULL,
                stop_order INT NOT NULL DEFAULT 0,
                INDEX idx_route (route_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                booking_id INT UNSIGNED NULL,
                rating TINYINT NOT NULL DEFAULT 5,
                comment TEXT NULL,
                is_public TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_booking (booking_id),
                INDEX idx_rating (rating)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            if (APP_ENV === 'development') {
                error_log('RideFlow DB migration warning: ' . $e->getMessage());
            }
        }
    }

    return $pdo;
}

/* ── Convenience helpers ─────────────────────────────────── */

/** Fetch a single row */
function db_row(string $sql, array $params = []): array|false {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetch();
}

/** Fetch all rows */
function db_all(string $sql, array $params = []): array {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Execute a write statement, return affected rows */
function db_exec(string $sql, array $params = []): int {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** Execute insert, return last insert ID */
function db_insert(string $sql, array $params = []): int {
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) db()->lastInsertId();
}

/** Get a single value */
function db_val(string $sql, array $params = []): mixed {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

/** Check whether a table exists in the active database */
function db_table_exists(string $tableName): bool {
    try {
        $schemaCondition = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql')
            ? 'table_schema = current_schema()'
            : 'table_schema = DATABASE()';
        $stmt = db()->prepare("SELECT 1 FROM information_schema.tables WHERE {$schemaCondition} AND table_name = ? LIMIT 1");
        $stmt->execute([$tableName]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}
