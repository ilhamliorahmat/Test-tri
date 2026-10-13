<?php
/**
 * Phortal - Photography Portal
 * Database Configuration & SQLite Auto-Bootstrap
 */

define('DB_FILE', __DIR__ . '/../../data/phortal.db');
define('UPLOADS_DIR', __DIR__ . '/../../uploads/photos/');

function get_db_connection() {
    static $db = null;
    if ($db === null) {
        // Ensure data and uploads directories exist
        if (!file_exists(dirname(DB_FILE))) {
            mkdir(dirname(DB_FILE), 0755, true);
        }
        if (!file_exists(UPLOADS_DIR)) {
            mkdir(UPLOADS_DIR, 0755, true);
        }

        try {
            $db = new PDO('sqlite:' . DB_FILE);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $db->exec('PRAGMA foreign_keys = ON;');
            $db->exec('PRAGMA journal_mode = WAL;');

            // Initialize database schema if tables do not exist
            bootstrap_database($db);
        } catch (PDOException $e) {
            die('Database connection error: ' . $e->getMessage());
        }
    }
    return $db;
}

function bootstrap_database($db) {
    // Users Table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin', 'curator')),
        full_name TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // Galleries Table
    $db->exec("CREATE TABLE IF NOT EXISTS galleries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        code TEXT UNIQUE NOT NULL,
        is_private INTEGER DEFAULT 1,
        created_by INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    );");

    // Photos Table
    $db->exec("CREATE TABLE IF NOT EXISTS photos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        gallery_id INTEGER NOT NULL,
        filename TEXT NOT NULL,
        original_name TEXT NOT NULL,
        title TEXT,
        caption TEXT,
        file_size INTEGER NOT NULL,
        width INTEGER NOT NULL,
        height INTEGER NOT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE
    );");

    // Watermarks Table
    $db->exec("CREATE TABLE IF NOT EXISTS watermarks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        gallery_id INTEGER UNIQUE NOT NULL,
        text TEXT NOT NULL DEFAULT 'PHORTAL COPYRIGHT',
        opacity INTEGER NOT NULL DEFAULT 50 CHECK(opacity BETWEEN 5 AND 100),
        font_size INTEGER NOT NULL DEFAULT 24 CHECK(font_size BETWEEN 12 AND 72),
        position TEXT NOT NULL DEFAULT 'bottom-right' CHECK(position IN ('center', 'bottom-right', 'bottom-left', 'top-right', 'top-left', 'tile')),
        color_hex TEXT NOT NULL DEFAULT '#FFFFFF',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE
    );");

    // Audit Logs Table
    $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        username TEXT NOT NULL,
        action TEXT NOT NULL,
        target_type TEXT NOT NULL,
        target_id TEXT,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // Seed default admin and curator accounts if empty
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $admin_pass = password_hash('admin123', PASSWORD_BCRYPT);
        $curator_pass = password_hash('curator123', PASSWORD_BCRYPT);

        $db->exec("INSERT INTO users (username, password_hash, role, full_name) VALUES
            ('admin', '$admin_pass', 'admin', 'System Administrator'),
            ('curator', '$curator_pass', 'curator', 'Lead Gallery Curator');");

        // Seed default sample gallery
        $db->exec("INSERT INTO galleries (title, description, code, is_private, created_by) VALUES
            ('Monolithic Structures', 'Exhibition of architectural minimalism and brutalist form study.', 'ARCH2026', 1, 1),
            ('Nature Cascades', 'Fine art landscape studies of waterfalls and ancient forests.', 'NAT889', 0, 2);");

        $db->exec("INSERT INTO watermarks (gallery_id, text, opacity, font_size, position) VALUES
            (1, 'PHORTAL ARCH2026 © ADMIN', 60, 28, 'bottom-right'),
            (2, 'CURATOR FINE ART COLLECTION', 50, 24, 'tile');");

        // Initial audit log record
        $db->exec("INSERT INTO audit_logs (user_id, username, action, target_type, target_id, details, ip_address) VALUES
            (1, 'system', 'SYSTEM_INIT', 'DATABASE', '0', 'Database bootstrapped with default schemas and seed accounts.', '127.0.0.1');");
    }
}
