<?php
class Database
{
    private static $connection = null;

    public static function getConnection()
    {
        if (self::$connection === null) {
            $host = 'localhost';
            $user = 'root';
            // Default XAMPP has no password for root
            $pass = '';

            $conn = new mysqli($host, $user, $pass);

            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

            // Ensure Database Exists (for convenience)
            $dbName = 'gestion-wallet-web';
            $conn->query("CREATE DATABASE IF NOT EXISTS `$dbName`");
            $conn->select_db($dbName);

            self::$connection = $conn;
        }
        return self::$connection;
    }
}
?>
