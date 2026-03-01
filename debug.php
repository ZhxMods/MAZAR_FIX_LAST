<?php
// Debug script to check for errors
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>MAZAR Debug Information</h2>";

// Check PHP version
echo "<h3>PHP Version</h3>";
echo "PHP Version: " . phpversion() . "<br>";
if (version_compare(phpversion(), '7.4.0', '<')) {
    echo "<span style='color:red'>WARNING: PHP version is too old. Minimum required: 7.4</span><br>";
} else {
    echo "<span style='color:green'>PHP version is OK</span><br>";
}

// Check required extensions
echo "<h3>Required Extensions</h3>";
$required = ['pdo', 'pdo_mysql', 'session', 'json', 'mbstring'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "<span style='color:green'>✓ {$ext}</span><br>";
    } else {
        echo "<span style='color:red'>✗ {$ext} - MISSING</span><br>";
    }
}

// Test config loading
echo "<h3>Config Test</h3>";
try {
    require_once __DIR__ . '/config.php';
    echo "<span style='color:green'>✓ config.php loaded successfully</span><br>";
} catch (Throwable $e) {
    echo "<span style='color:red'>✗ config.php error: " . $e->getMessage() . "</span><br>";
}

// Test database connection
echo "<h3>Database Test</h3>";
try {
    require_once __DIR__ . '/includes/db.php';
    $db = getDB();
    echo "<span style='color:green'>✓ Database connected successfully</span><br>";
    
    // Test query
    $stmt = $db->query("SELECT COUNT(*) FROM levels");
    $count = $stmt->fetchColumn();
    echo "Levels count: {$count}<br>";
} catch (Throwable $e) {
    echo "<span style='color:red'>✗ Database error: " . $e->getMessage() . "</span><br>";
}

// Test functions loading
echo "<h3>Functions Test</h3>";
try {
    require_once __DIR__ . '/includes/functions.php';
    echo "<span style='color:green'>✓ functions.php loaded successfully</span><br>";
    
    // Test translation function
    $test = t('site_name');
    echo "Translation test: {$test}<br>";
} catch (Throwable $e) {
    echo "<span style='color:red'>✗ functions.php error: " . $e->getMessage() . "</span><br>";
}

// Check file permissions
echo "<h3>File Permissions</h3>";
$dirs = ['logs', 'assets', 'assets/images', 'assets/css', 'assets/js'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        if (is_writable($path)) {
            echo "<span style='color:green'>✓ {$dir} - writable</span><br>";
        } else {
            echo "<span style='color:orange'>⚠ {$dir} - not writable</span><br>";
        }
    } else {
        echo "<span style='color:red'>✗ {$dir} - does not exist</span><br>";
    }
}

echo "<h3>Session Test</h3>";
if (isset($_SESSION)) {
    echo "<span style='color:green'>✓ Session is active</span><br>";
    echo "Session ID: " . session_id() . "<br>";
} else {
    echo "<span style='color:red'>✗ Session not started</span><br>";
}

echo "<hr><p>Debug complete. If you see any red errors above, those need to be fixed.</p>";
