<?php
/**
 * Database Connection Test
 * Use this file to test if your database connection is working
 * DELETE THIS FILE after testing for security!
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; border: 2px solid #28a745; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 2px solid #dc3545; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 2px solid #17a2b8; padding: 15px; border-radius: 5px; margin: 10px 0; }
        h1 { color: #333; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 Database Connection Test</h1>
    <p class="info"><strong>⚠️ SECURITY WARNING:</strong> Delete this file immediately after testing!</p>
    
    <?php
    // Test 1: Check if env.php exists
    echo "<h2>Test 1: Environment File</h2>";
    if (file_exists(__DIR__ . '/env.php')) {
        echo "<div class='success'>✅ env.php file exists</div>";
        require_once __DIR__ . '/env.php';
        
        // Display configuration (masked for security)
        echo "<div class='info'>";
        echo "<strong>Configuration:</strong><br>";
        echo "DB_HOST: " . htmlspecialchars(DB_HOST) . "<br>";
        echo "DB_NAME: " . htmlspecialchars(DB_NAME) . "<br>";
        echo "DB_USERNAME: " . htmlspecialchars(substr(DB_USERNAME, 0, 3)) . "***<br>";
        echo "DB_PASSWORD: " . (empty(DB_PASSWORD) ? "<span style='color:red;'>EMPTY - This might be the problem!</span>" : "***") . "<br>";
        echo "APP_ENV: " . (defined('APP_ENV') ? htmlspecialchars(APP_ENV) : 'Not defined') . "<br>";
        echo "APP_DEBUG: " . (defined('APP_DEBUG') ? (APP_DEBUG ? 'true' : 'false') : 'Not defined') . "<br>";
        echo "</div>";
    } else {
        echo "<div class='error'>❌ env.php file NOT FOUND!</div>";
        echo "<div class='info'>Create env.php from env.example.php</div>";
        die();
    }
    
    // Test 2: Database Connection
    echo "<h2>Test 2: Database Connection</h2>";
    try {
        $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD);
        
        if ($conn->connect_error) {
            echo "<div class='error'>❌ Connection failed: " . htmlspecialchars($conn->connect_error) . "</div>";
            echo "<div class='info'>";
            echo "<strong>Common issues:</strong><br>";
            echo "1. Wrong database credentials in env.php<br>";
            echo "2. Database server is down<br>";
            echo "3. Database user doesn't have permissions<br>";
            echo "4. Database host is incorrect (should usually be 'localhost')<br>";
            echo "</div>";
        } else {
            echo "<div class='success'>✅ Connected to MySQL server successfully</div>";
            
            // Test 3: Database Selection
            echo "<h2>Test 3: Database Selection</h2>";
            if ($conn->select_db(DB_NAME)) {
                echo "<div class='success'>✅ Database '" . htmlspecialchars(DB_NAME) . "' selected successfully</div>";
                
                // Test 4: Check if tables exist
                echo "<h2>Test 4: Database Tables</h2>";
                $tables_result = $conn->query("SHOW TABLES");
                if ($tables_result) {
                    $table_count = $tables_result->num_rows;
                    echo "<div class='success'>✅ Found $table_count table(s) in database</div>";
                    
                    if ($table_count > 0) {
                        echo "<div class='info'>";
                        echo "<strong>Tables found:</strong><br>";
                        while ($row = $tables_result->fetch_array()) {
                            echo "- " . htmlspecialchars($row[0]) . "<br>";
                        }
                        echo "</div>";
                    } else {
                        echo "<div class='info'>⚠️ No tables found. The database will auto-create tables on first page load.</div>";
                    }
                } else {
                    echo "<div class='error'>❌ Error checking tables: " . htmlspecialchars($conn->error) . "</div>";
                }
                
                // Test 5: Check users table specifically
                echo "<h2>Test 5: Users Table</h2>";
                $users_check = $conn->query("SHOW TABLES LIKE 'users'");
                if ($users_check && $users_check->num_rows > 0) {
                    echo "<div class='success'>✅ Users table exists</div>";
                    
                    $user_count = $conn->query("SELECT COUNT(*) as count FROM users");
                    if ($user_count) {
                        $count = $user_count->fetch_assoc()['count'];
                        echo "<div class='info'>Total users: $count</div>";
                    }
                } else {
                    echo "<div class='info'>⚠️ Users table doesn't exist yet. It will be created automatically.</div>";
                }
                
            } else {
                echo "<div class='error'>❌ Cannot select database '" . htmlspecialchars(DB_NAME) . "'</div>";
                echo "<div class='info'>";
                echo "<strong>Possible solutions:</strong><br>";
                echo "1. Database doesn't exist - create it in cPanel<br>";
                echo "2. Database name is incorrect in env.php<br>";
                echo "3. User doesn't have permission to access this database<br>";
                echo "</div>";
            }
            
            $conn->close();
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Exception: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // Test 6: File Permissions
    echo "<h2>Test 6: File Permissions</h2>";
    $writable_dirs = ['images'];
    foreach ($writable_dirs as $dir) {
        $path = __DIR__ . '/' . $dir;
        if (is_dir($path)) {
            if (is_writable($path)) {
                echo "<div class='success'>✅ $dir/ directory is writable</div>";
            } else {
                echo "<div class='error'>❌ $dir/ directory is NOT writable (set permissions to 755)</div>";
            }
        } else {
            echo "<div class='info'>⚠️ $dir/ directory doesn't exist</div>";
        }
    }
    
    // Test 7: Required Files
    echo "<h2>Test 7: Required Files</h2>";
    $required_files = ['db.php', 'config.php', 'index.php', '.htaccess'];
    foreach ($required_files as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            echo "<div class='success'>✅ $file exists</div>";
        } else {
            echo "<div class='error'>❌ $file is MISSING!</div>";
        }
    }
    ?>
    
    <hr>
    <h2>Summary</h2>
    <p class="info">
        <strong>Next Steps:</strong><br>
        1. Fix any errors shown above<br>
        2. If database connection works, try visiting your homepage<br>
        3. <strong>DELETE THIS FILE</strong> after testing for security<br>
        4. Check error logs in cPanel if issues persist
    </p>
    
    <p><small>Generated: <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>





