<?php

namespace App;

use Exception;
use SQLite3;

class CreateDB
{
    private SQLite3 $db;

    public function __construct($location)
    {
        $this->db = new SQLite3($location);
        $this->db->enableExceptions(true);
    }

    public function make(): void
    {
        $this->createTables();
        $this->createAllUsers();
    }

    private function createTables(): void
    {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL 
)");

            $this->db->exec("CREATE TABLE IF NOT EXISTS wallet (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    balance REAL NOT NULL,
    FOREIGN KEY(username) REFERENCES users(username)
)");

            $this->db->exec("CREATE TABLE IF NOT EXISTS transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    type TEXT NOT NULL,
    symbol TEXT NOT NULL,
    amount REAL NOT NULL,
    price REAL NOT NULL,
    value REAL NOT NULL,
    time TEXT NOT NULL,
    FOREIGN KEY (username) REFERENCES users(username)
)");
        } catch (Exception $e) {
        }
    }

    private function createUser(string $userName, string $password, float $balance): void
    {
        try {
            $encrypt = md5($password);
            $store = $this->db->prepare("INSERT INTO users(username, password) VALUES (:username, :password)");
            $store->bindValue(':username', $userName);
            $store->bindValue(':password', $encrypt);
            $store->execute();
            $store = $this->db->prepare("INSERT INTO wallet(username, balance) VALUES (:username,:balance)");
            $store->bindValue(':username', $userName);
            $store->bindValue(':balance', $balance, SQLITE3_FLOAT);
            $store->execute();
        } catch (Exception $e) {

        }
    }

    private function createAllUsers(): void
    {
        $users = [
            ['username' => 'John', 'password' => 'p@55W0RD', 'balance' => 1000],
            ['username' => 'Steve', 'password' => 'pass', 'balance' => 10000],
            ['username' => 'J', 'password' => '123456', 'balance' => 1500],
            ['username' => 'Joe', 'password' => 'qwerty', 'balance' => 2500],
            ['username' => 'Jane', 'password' => '11235813', 'balance' => 5000],
            ['username' => 'A', 'password' => 'A', 'balance' => 100],
        ];
        foreach ($users as $user) {
            $this->createUser($user['username'], $user['password'], $user['balance']);
        }
    }
}