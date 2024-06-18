<?php

namespace App;

use Exception;
use SQLite3;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

class Trade
{
    private ApiClient $apiClient;
    private SQLite3 $db;
    private string $userName;

    public function __construct(ApiClient $apiClient, string $userName)
    {
        $this->apiClient = $apiClient;
        $this->db = new SQLite3(__DIR__ . '/../storage/database.sqlite');
        $this->userName = $userName;
    }

    public function list(): void
    {
        try {
            $data = $this->apiClient->getList(1, 10, 'USD');
            if (isset($data["data"])) {
                $output = new ConsoleOutput();
                $table = new Table($output);
                $table->setHeaders(["Rank", "Name", "Symbol", "Price"]);
                foreach ($data["data"] as $crypto) {
                    $currency = new Currency(
                        $crypto["name"],
                        $crypto["symbol"],
                        $crypto["cmc_rank"],
                        $crypto["quote"]["USD"]["price"]
                    );
                    $table->addRow([
                        $currency->getRank(),
                        $currency->getName(),
                        $currency->getSymbol(),
                        $currency->getPrice()
                    ]);
                }
                $table->render();
            } else {
                exit ("error getting data");
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    public function search(string $symbol): void
    {
        try {
            $data = $this->apiClient->getSymbol($symbol, 'USD');
            if (isset($data["data"])) {
                $crypto = $data["data"][$symbol];
                $currency = new Currency(
                    $crypto["name"],
                    $crypto["symbol"],
                    $crypto["cmc_rank"],
                    $crypto["quote"]["USD"]["price"]
                );
                $output = new ConsoleOutput();
                $table = new Table($output);
                $table->setHeaders(["Rank", "Name", "Symbol", "Price"]);
                $table->addRow([
                    $currency->getRank(),
                    $currency->getName(),
                    $currency->getSymbol(),
                    $currency->getPrice()
                ]);
                $table->render();
            } else {
                exit ("error getting data");
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    private function getBalance(): float
    {
        $userBalance = $this->db->prepare("SELECT balance FROM wallet WHERE username = :username LIMIT 1");
        $userBalance->bindValue(':username', $this->userName);
        $result = $userBalance->execute()->fetchArray(SQLITE3_ASSOC);
        return $result["balance"];
    }

    private function updateBalance(float $amount): void
    {
        $update = $this->db->prepare("UPDATE wallet SET balance = :amount WHERE username = :username");
        $update->bindValue(':amount', $amount, SQLITE3_FLOAT);
        $update->bindValue(':username', $this->userName);
        $update->execute();
    }

    private function getTransactions(): array
    {
        $actions = $this->db->prepare("SELECT * FROM transactions WHERE username = :username");
        $actions->bindValue(':username', $this->userName);
        $result = $actions->execute();
        $transactions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $transactions[] = $row;
        }
        return $transactions;
    }

    private function saveTransactions(array $transaction): void
    {
        $save = $this->db->prepare("INSERT INTO transactions (username, type, symbol, amount, price, value, time) 
VALUES (:username, :type, :symbol, :amount, :price, :value, :time)");
        $save->bindValue(':username', $this->userName);
        $save->bindValue(':type', $transaction['type']);
        $save->bindValue(':symbol', $transaction['symbol']);
        $save->bindValue(':amount', $transaction['amount'], SQLITE3_FLOAT);
        $save->bindValue(':price', $transaction['price'], SQLITE3_FLOAT);
        $save->bindValue(':value', $transaction['value'], SQLITE3_FLOAT);
        $save->bindValue(':time', $transaction['time']);
        $save->execute();
    }

    public function purchase(string $symbol): void
    {
        try {
            $data = $this->apiClient->getSymbol($symbol, "USD");
            if (isset($data['data'][$symbol])) {
                $amount = (float)readline("Enter amount of $symbol to buy ");
                if ($amount <= 0) {
                    echo "Enter positive amount " . PHP_EOL;
                    return;
                }
                $price = $data["data"][$symbol]["quote"]["USD"]["price"];
                $cost = $price * $amount;
                $balance = $this->getBalance();
                if ($balance < $cost) {
                    echo "Insufficient funds to buy $amount $symbol " . PHP_EOL;
                    return;
                }
                $this->updateBalance($balance - $cost);
                $this->saveTransactions([
                    'type' => 'purchase',
                    'symbol' => $symbol,
                    'amount' => $amount,
                    'price' => $price,
                    'value' => $cost,
                    'time' => date("Y-m-d H:i:s")
                ]);
                echo "Purchased $amount $symbol for \$$cost" . PHP_EOL;
            } else {
                echo $symbol . " not found" . PHP_EOL;
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    public function sell(string $symbol): void
    {
        try {
            $data = $this->apiClient->getSymbol($symbol, "USD");
            if (isset($data['data'][$symbol])) {
                $amount = (float)readline("Enter amount of $symbol to sell ");
                if ($amount <= 0) {
                    echo "Enter positive amount " . PHP_EOL;
                    return;
                }
                $price = $data["data"][$symbol]["quote"]["USD"]["price"];
                $value = $price * $amount;
                $bought = 0;
                $sold = 0;
                $transactions = $this->getTransactions();
                foreach ($transactions as $transaction) {
                    if ($transaction['type'] === "purchase" && $transaction['symbol'] === $symbol) {
                        $bought += $transaction['amount'];
                    } elseif ($transaction['type'] === "sell" && $transaction['symbol'] === $symbol) {
                        $sold += $transaction['amount'];
                    }
                }
                $availableAmount = $bought - $sold;
                if ($amount > $availableAmount) {
                    echo "Insufficient amount of $symbol to sell " . PHP_EOL;
                    return;
                }
                $this->updateBalance($this->getBalance() + $value);
                $this->saveTransactions([
                    'type' => 'sell',
                    'symbol' => $symbol,
                    'amount' => $amount,
                    'price' => $price,
                    'value' => $value,
                    'time' => date('Y-m-d H:i:s')
                ]);
                echo "Sold $amount $symbol for \$$value" . PHP_EOL;
            } else {
                echo $symbol . " not found" . PHP_EOL;
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    public function displayWallet(): void
    {
        $balance = $this->getBalance();
        echo "Current balance is $" . $balance . PHP_EOL;
        $holding = [];
        $transactions = $this->getTransactions();
        foreach ($transactions as $transaction) {
            $symbol = $transaction['symbol'];
            if (!isset($holding[$symbol])) {
                $holding[$symbol] = ['amount' => 0, 'totalSpent' => 0];
            }
            if ($transaction['type'] === 'purchase') {
                $holding[$symbol]['amount'] += $transaction['amount'];
                $holding[$symbol]['totalSpent'] += $transaction['amount'] * $transaction['price'];
            } elseif ($transaction['type'] === "sell") {
                $holding[$symbol]['amount'] -= $transaction['amount'];
                $holding[$symbol]['totalSpent'] -= $transaction['amount'] * $transaction['price'];
            }
        }
        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders(["Symbol", "Amount", "Average purchase price", "Current Price", "Profit (%)"]);
        foreach ($holding as $symbol => $amount) {
            if ($amount['amount'] > 0) {
                $average = $amount['totalSpent'] / $amount['amount'];
                $currentData = $this->apiClient->getSymbol($symbol, "USD");
                $currentPrice = $currentData['data'][$symbol]['quote']['USD']['price'];
                $profit = (($currentPrice - $average) / $average) * 100;
                $table->addRow([
                    $symbol,
                    $amount['amount'],
                    $average,
                    $currentPrice,
                    number_format($profit, 2)
                ]);
            }
        }
        $table->render();
    }

    public function displayTransactions(): void
    {
        $transactions = $this->getTransactions();
        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders(["Type", "Symbol", "Amount", "Price", "Value", "Time"]);
        foreach ($transactions as $transaction) {
            $table->addRow([
                ucfirst($transaction["type"]),
                $transaction['symbol'],
                $transaction['amount'],
                $transaction['price'],
                $transaction['value'],
                $transaction['time']
            ]);
        }
        $table->render();
    }
}