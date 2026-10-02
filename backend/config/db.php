<?php
/**
 * FRESH SCENTS - Muunganisho wa Database (Database Connection)
 * Badilisha taarifa hizi kulingana na server yako (hosting/localhost).
 */

// ------- WEKA TAARIFA ZA DATABASE YAKO HAPA -------
define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_NAME', 'freshscents_new_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
// ---------------------------------------------------

// Saa ya Tanzania (EAT, UTC+3) — inasahihisha muda unaoonekana kwenye mfumo mzima
date_default_timezone_set('Africa/Dar_es_Salaam');

// Weka DEBUG_MODE = true wakati wa usanidi (kukusaidia kuona hitilafu halisi).
// Ukishakamilisha usanidi kwenye server ya kutumika kazini (production), badilisha kuwa false.
define('DEBUG_MODE', true);

// Njia ya folda ya uploads (picha za bidhaa)
define('UPLOAD_DIR', __DIR__ . '/../uploads/products/');
define('UPLOAD_URL', 'uploads/products/');

/**
 * Jaribu kuunganisha na DB_HOST kwanza; kama itashindikana kwa sababu
 * server haisikilizi (connection refused), jaribu tena kwa 127.0.0.1.
 * Hii inasaidia kwenye baadhi ya server ambazo "localhost" hazitambuliki
 * sahihi kama unix socket au TCP.
 */
function fs_connect_db() {
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    $hostsToTry = [DB_HOST];
    if (DB_HOST !== '127.0.0.1') {
        $hostsToTry[] = '127.0.0.1';
    }

    $lastError = null;
    foreach ($hostsToTry as $host) {
        try {
            $dsn = "mysql:host=$host;port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Lazimisha MySQL itumie saa ya Tanzania (+03:00) kwa NOW()/CURRENT_TIMESTAMP
            // ili tarehe/muda zinazohifadhiwa zilingane na muda halisi wa Tanzania.
            $conn->exec("SET time_zone = '+03:00'");
            return $conn;
        } catch (PDOException $e) {
            $lastError = $e;
        }
    }
    throw $lastError;
}

try {
    $pdo = fs_connect_db();
} catch (PDOException $e) {
    http_response_code(500);
    $rawMessage = $e->getMessage();

    // Tengeneza ujumbe unaosaidia zaidi kutegemea aina ya hitilafu
    if (strpos($rawMessage, 'actively refused') !== false || strpos($rawMessage, '2002') !== false) {
        $hint = 'MySQL/MariaDB haiendeshi (haipo "running") kwenye server yako, au haisikilizi kwenye port ' . DB_PORT . '. Endesha: sudo systemctl start mysql (au mariadb), kisha jaribu tena.';
    } elseif (strpos($rawMessage, 'Access denied') !== false) {
        $hint = 'DB_USER au DB_PASS si sahihi. Angalia mtumiaji uliyemtengeneza kwenye MySQL na taarifa zilizopo backend/config/db.php.';
    } elseif (strpos($rawMessage, 'Unknown database') !== false) {
        $hint = 'Database "' . DB_NAME . '" haijaundwa bado. Ingiza database/schema.sql kupitia phpMyAdmin (Import).';
    } else {
        $hint = 'Angalia MySQL inaendesha, database imeundwa (schema.sql imeingizwa), na taarifa za backend/config/db.php ni sahihi.';
    }

    $debugDetail = DEBUG_MODE ? $rawMessage : '';

    die('<div style="font-family:sans-serif;max-width:680px;margin:70px auto;padding:0 20px;line-height:1.6">
        <h2 style="color:#c0392b">Hitilafu ya Database</h2>
        <p>Mfumo umeshindwa kuunganisha na database.</p>
        <p style="background:#fff3cd;border:1px solid #ffe08a;padding:12px 16px;border-radius:8px"><strong>Fanya hivi:</strong> ' . htmlspecialchars($hint) . '</p>
        <ol>
          <li>MySQL/MariaDB inaendesha (running) kwenye server yako &mdash; <code>sudo systemctl status mysql</code></li>
          <li>Umeingiza <code>database/schema.sql</code> kwenye phpMyAdmin (Import).</li>
          <li>Taarifa za <code>backend/config/db.php</code> (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS) ni sahihi.</li>
        </ol>
        ' . ($debugDetail ? '<p><strong>Hitilafu halisi (kwa msanidi):</strong><br><code style="color:#c0392b">' . htmlspecialchars($debugDetail) . '</code></p>' : '') . '
        <p style="font-size:13px;color:#8a7d5f">Kidokezo: kwenye Ubuntu/Debian, mtumiaji wa MySQL "root" mara nyingi hauruhusu kuingia kwa password kupitia programu (anatumia "auth_socket"). Tengeneza mtumiaji mwingine maalumu kwa ajili ya mfumo huu, mfano:<br>
        <code>CREATE USER \'freshscents\'@\'localhost\' IDENTIFIED BY \'password_yako\';<br>
        GRANT ALL PRIVILEGES ON fresh_scents_db.* TO \'freshscents\'@\'localhost\';<br>
        FLUSH PRIVILEGES;</code><br>
        Kisha weka DB_USER na DB_PASS hizo mpya kwenye <code>backend/config/db.php</code>.</p>
        </div>');
}
