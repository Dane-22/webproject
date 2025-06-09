<?php
class DatabaseBackupRestore {
    private $dbHost;
    private $dbUser;
    private $dbPass;
    private $dbName;
    private $backupDir;
    private $conn;

    public function __construct($host, $user, $password, $database, $backupDir = 'backups') {
        $this->dbHost = $host;
        $this->dbUser = $user;
        $this->dbPass = $password;
        $this->dbName = $database;
        $this->backupDir = $backupDir;

        // Create backup directory if it doesn't exist
        if (!file_exists($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        // Establish database connection
        $this->conn = new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    // Backup database
    public function backup($customName = null) {
        $backupFileName = $this->backupDir . '/' . ($customName ?: $this->dbName . '_' . date('Y-m-d_H-i-s')) . '.sql';

        // Get all tables
        $tables = array();
        $result = $this->conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }

        $output = '';
        foreach ($tables as $table) {
            // Table structure
            $output .= "--\n-- Table structure for table `$table`\n--\n\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $createTable = $this->conn->query("SHOW CREATE TABLE `$table`");
            $row = $createTable->fetch_row();
            $output .= $row[1] . ";\n\n";

            // Table data
            $output .= "--\n-- Dumping data for table `$table`\n--\n\n";
            $rows = $this->conn->query("SELECT * FROM `$table`");
            $numFields = $rows->field_count;

            while ($data = $rows->fetch_row()) {
                $output .= "INSERT INTO `$table` VALUES(";
                for ($i = 0; $i < $numFields; $i++) {
                    $data[$i] = addslashes($data[$i]);
                    $data[$i] = str_replace("\n", "\\n", $data[$i]);
                    if (isset($data[$i])) {
                        $output .= '"' . $data[$i] . '"';
                    } else {
                        $output .= 'NULL';
                    }
                    if ($i < ($numFields - 1)) {
                        $output .= ',';
                    }
                }
                $output .= ");\n";
            }
            $output .= "\n";
        }

        // Save to file
        if (file_put_contents($backupFileName, $output)) {
            return "Backup created successfully: " . realpath($backupFileName);
        } else {
            return "Error creating backup file";
        }
    }

    // Restore database
    public function restore($backupFile) {
        if (!file_exists($backupFile)) {
            return "Backup file not found";
        }

        // Read backup file
        $sql = file_get_contents($backupFile);

        // Execute queries
        if ($this->conn->multi_query($sql)) {
            do {
                // Clear results
                if ($result = $this->conn->store_result()) {
                    $result->free();
                }
            } while ($this->conn->more_results() && $this->conn->next_result());

            return "Database restored successfully from: " . realpath($backupFile);
        } else {
            return "Error restoring database: " . $this->conn->error;
        }
    }

    // List available backups
    public function listBackups() {
        $backups = array();
        $files = scandir($this->backupDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $backups[] = $file;
            }
        }
        return $backups;
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Example usage:
if (isset($_GET['action'])) {
    $db = new DatabaseBackupRestore('localhost', 'username', 'password', 'database_name');

    switch ($_GET['action']) {
        case 'backup':
            echo $db->backup();
            break;
        case 'restore':
            if (isset($_GET['file'])) {
                echo $db->restore($db->backupDir . '/' . $_GET['file']);
            } else {
                echo "Please specify a backup file";
            }
            break;
        case 'list':
            echo "Available backups:<br>";
            foreach ($db->listBackups() as $backup) {
                echo "<a href='?action=restore&file=$backup'>$backup</a><br>";
            }
            break;
        default:
            echo "Invalid action";
    }
} else {
    echo "
    <h1>Database Backup and Restore</h1>
    <a href='?action=backup'>Create Backup</a><br>
    <a href='?action=list'>List Backups</a>
    ";
}
?>



















other file

public function backupWithMySQLDump($customName = null) {
    $backupFileName = $this->backupDir . '/' . ($customName ?: $this->dbName . '_' . date('Y-m-d_H-i-s')) . '.sql';
    $command = "mysqldump --user={$this->dbUser} --password={$this->dbPass} --host={$this->dbHost} {$this->dbName} > {$backupFileName}";
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0) {
        return "Backup created successfully: " . realpath($backupFileName);
    } else {
        return "Error creating backup";
    }
}