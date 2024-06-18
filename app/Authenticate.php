<?php

namespace App;

use SQLite3;

class Authenticate
{
    private string $location;
    private SQLITE3 $db;
    private int $max = 3;

    public function __construct(string $location)
    {
        $this->location = $location;
        $this->db = new SQLite3($this->location);
        $this->db->enableExceptions(true);
    }

    private function authorize(string $userName, string $password): bool
    {
        $check = $this->db->
        prepare("SELECT COUNT(*) as count FROM users WHERE username = :username AND password = :password");
        $check->bindValue(':username', $userName);
        $check->bindValue(':password', $password);
        $result = $check->execute()->fetchArray(SQLITE3_ASSOC);
        return $result['count'] > 0;
    }

    public function login(): ?string
    {
        $attempts = 0;
        while ($attempts < $this->max) {
            $userName = readline("Enter username: ");
            $password = readline("Enter password: ");
            $encrypted = md5($password);
            if ($this->authorize($userName, $encrypted)) {
                echo "Welcome $userName!" . PHP_EOL;
                return $userName;
            } else {
                echo "Invalid username or password" . PHP_EOL;
                $attempts++;
            }
        }
        exit("Too many attempts");
    }

    public function __destruct()
    {
        $this->db->close();
    }
}