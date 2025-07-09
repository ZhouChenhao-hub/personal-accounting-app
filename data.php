<?php

class AccountingDB {
    private $pdo;
    
    public function __construct() {
        $this->initDatabase();
    }
    
    private function initDatabase() {
        try {
            $this->pdo = new PDO('sqlite:accounting.db');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTables();
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    private function createTables() {
        // 创建账户表
        $sql = "CREATE TABLE IF NOT EXISTS accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            type TEXT NOT NULL,
            balance DECIMAL(10,2) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
        
        // 创建交易表
        $sql = "CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            type TEXT NOT NULL CHECK (type IN ('income', 'expense')),
            category1 TEXT NOT NULL,
            category2 TEXT NOT NULL,
            category3 TEXT NOT NULL,
            description TEXT,
            date DATE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (account_id) REFERENCES accounts(id)
        )";
        $this->pdo->exec($sql);
        
    }
    
    public function addAccount($name, $type) {
        try {
            $sql = "INSERT INTO accounts (name, type) VALUES (?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$name, $type]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('添加账户失败: ' . $e->getMessage());
        }
    }
    
    public function addTransaction($accountId, $amount, $type, $category1, $category2, $category3, $description = '', $date = null) {
        try {
            if ($date === null) {
                $date = date('Y-m-d');
            }
            
            $this->pdo->beginTransaction();
            
            // 添加交易记录
            $sql = "INSERT INTO transactions (account_id, amount, type, category1, category2, category3, description, date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$accountId, $amount, $type, $category1, $category2, $category3, $description, $date]);
            
            // 更新账户余额
            $balanceChange = $type === 'income' ? $amount : -$amount;
            $sql = "UPDATE accounts SET balance = balance + ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$balanceChange, $accountId]);
            
            $this->pdo->commit();
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->pdo->rollback();
            throw new Exception('添加交易失败: ' . $e->getMessage());
        }
    }
    
    public function getAccounts() {
        try {
            $sql = "SELECT * FROM accounts ORDER BY created_at DESC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取账户失败: ' . $e->getMessage());
        }
    }
    
    public function getTransactions($limit = 50) {
        try {
            $sql = "SELECT t.*, a.name as account_name 
                    FROM transactions t 
                    JOIN accounts a ON t.account_id = a.id 
                    ORDER BY t.date DESC, t.created_at DESC 
                    LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取交易记录失败: ' . $e->getMessage());
        }
    }
    
    public function getStats() {
        try {
            $currentMonth = date('Y-m');
            $stats = [];
            
            // 总资产
            $sql = "SELECT SUM(balance) as total_balance FROM accounts";
            $stmt = $this->pdo->query($sql);
            $stats['total_balance'] = $stmt->fetchColumn() ?: 0;
            
            // 本月收入
            $sql = "SELECT SUM(amount) as monthly_income 
                    FROM transactions 
                    WHERE type = 'income' AND date LIKE ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$currentMonth . '%']);
            $stats['monthly_income'] = $stmt->fetchColumn() ?: 0;
            
            // 本月支出
            $sql = "SELECT SUM(amount) as monthly_expense 
                    FROM transactions 
                    WHERE type = 'expense' AND date LIKE ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$currentMonth . '%']);
            $stats['monthly_expense'] = $stmt->fetchColumn() ?: 0;
            
            // 本月净值
            $stats['monthly_net'] = $stats['monthly_income'] - $stats['monthly_expense'];
            
            return $stats;
        } catch (PDOException $e) {
            throw new Exception('获取统计数据失败: ' . $e->getMessage());
        }
    }
    
    public function getExpenseByCategory() {
        try {
            $currentMonth = date('Y-m');
            $sql = "SELECT category1, SUM(amount) as amount 
                    FROM transactions 
                    WHERE type = 'expense' AND date LIKE ? 
                    GROUP BY category1 
                    ORDER BY amount DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$currentMonth . '%']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取支出分类失败: ' . $e->getMessage());
        }
    }
    
    public function getIncomeByCategory() {
        try {
            $currentMonth = date('Y-m');
            $sql = "SELECT category1, SUM(amount) as amount 
                    FROM transactions 
                    WHERE type = 'income' AND date LIKE ? 
                    GROUP BY category1 
                    ORDER BY amount DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$currentMonth . '%']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取收入分类失败: ' . $e->getMessage());
        }
    }
    
    public function getMonthlyTrend($months = 12) {
        try {
            $sql = "SELECT 
                        DATE_FORMAT(date, '%Y-%m') as month,
                        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE date >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                    GROUP BY DATE_FORMAT(date, '%Y-%m')
                    ORDER BY month";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$months]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // SQLite不支持DATE_FORMAT，使用strftime
            $sql = "SELECT 
                        strftime('%Y-%m', date) as month,
                        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE date >= date('now', '-' || ? || ' months')
                    GROUP BY strftime('%Y-%m', date)
                    ORDER BY month";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$months]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    public function getIncomeExpenseTrend($period = 'month') {
        try {
            $dateFormat = '';
            $dateCondition = '';
            
            switch ($period) {
                case 'week':
                    $dateFormat = "date";
                    $dateCondition = "date >= date('now', '-7 days')";
                    break;
                case 'month':
                    $dateFormat = "strftime('%Y-%m-%d', date)";
                    $dateCondition = "date >= date('now', '-30 days')";
                    break;
                case 'year':
                    $dateFormat = "strftime('%Y-%m', date)";
                    $dateCondition = "date >= date('now', '-1 year')";
                    break;
                default:
                    $dateFormat = "strftime('%Y-%m-%d', date)";
                    $dateCondition = "date >= date('now', '-30 days')";
            }
            
            $sql = "SELECT 
                        {$dateFormat} as period,
                        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE {$dateCondition}
                    GROUP BY {$dateFormat}
                    ORDER BY period";
            
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取收支趋势失败: ' . $e->getMessage());
        }
    }
    
    public function searchTransactions($keyword = '', $accountId = null, $type = null, $category1 = null, $category2 = null, $category3 = null, $dateFrom = null, $dateTo = null) {
        try {
            $sql = "SELECT t.*, a.name as account_name 
                    FROM transactions t 
                    JOIN accounts a ON t.account_id = a.id 
                    WHERE 1=1";
            $params = [];
            
            if ($keyword) {
                $sql .= " AND (t.description LIKE ? OR t.category1 LIKE ? OR t.category2 LIKE ? OR t.category3 LIKE ?)";
                $params[] = "%$keyword%";
                $params[] = "%$keyword%";
                $params[] = "%$keyword%";
                $params[] = "%$keyword%";
            }
            
            if ($accountId) {
                $sql .= " AND t.account_id = ?";
                $params[] = $accountId;
            }
            
            if ($type) {
                $sql .= " AND t.type = ?";
                $params[] = $type;
            }
            
            if ($category1) {
                $sql .= " AND t.category1 = ?";
                $params[] = $category1;
            }
            
            if ($category2) {
                $sql .= " AND t.category2 = ?";
                $params[] = $category2;
            }
            
            if ($category3) {
                $sql .= " AND t.category3 = ?";
                $params[] = $category3;
            }
            
            if ($dateFrom) {
                $sql .= " AND t.date >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $sql .= " AND t.date <= ?";
                $params[] = $dateTo;
            }
            
            $sql .= " ORDER BY t.date DESC, t.created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('搜索交易失败: ' . $e->getMessage());
        }
    }
    
    public function deleteTransaction($id) {
        try {
            $this->pdo->beginTransaction();
            
            // 获取交易信息
            $sql = "SELECT * FROM transactions WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception('交易不存在');
            }
            
            // 删除交易
            $sql = "DELETE FROM transactions WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            
            // 恢复账户余额
            $balanceChange = $transaction['type'] === 'income' ? -$transaction['amount'] : $transaction['amount'];
            $sql = "UPDATE accounts SET balance = balance + ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$balanceChange, $transaction['account_id']]);
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollback();
            throw new Exception('删除交易失败: ' . $e->getMessage());
        }
    }
    
    public function updateAccount($id, $name, $type) {
        try {
            $sql = "UPDATE accounts SET name = ?, type = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$name, $type, $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception('更新账户失败: ' . $e->getMessage());
        }
    }
    
    public function deleteAccount($id) {
        try {
            $this->pdo->beginTransaction();
            
            // 检查账户是否有交易记录
            $sql = "SELECT COUNT(*) FROM transactions WHERE account_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception('无法删除有交易记录的账户');
            }
            
            // 删除账户
            $sql = "DELETE FROM accounts WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollback();
            throw new Exception('删除账户失败: ' . $e->getMessage());
        }
    }
    
    public function getAccountById($id) {
        try {
            $sql = "SELECT * FROM accounts WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取账户失败: ' . $e->getMessage());
        }
    }
    
    public function getTransactionById($id) {
        try {
            $sql = "SELECT t.*, a.name as account_name 
                    FROM transactions t 
                    JOIN accounts a ON t.account_id = a.id 
                    WHERE t.id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取交易失败: ' . $e->getMessage());
        }
    }
    
    public function adjustAccountBalance($accountId, $newBalance, $reason = '') {
        try {
            $this->pdo->beginTransaction();
            
            // 获取当前账户信息
            $account = $this->getAccountById($accountId);
            if (!$account) {
                throw new Exception('账户不存在');
            }
            
            $currentBalance = $account['balance'];
            $adjustment = $newBalance - $currentBalance;
            
            // 更新账户余额
            $sql = "UPDATE accounts SET balance = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$newBalance, $accountId]);
            
            // 记录余额调整交易
            if ($adjustment != 0) {
                $type = $adjustment > 0 ? 'income' : 'expense';
                $amount = abs($adjustment);
                $description = '余额调整: ' . $reason . ' (从 ¥' . number_format($currentBalance, 2) . ' 调整到 ¥' . number_format($newBalance, 2) . ')';
                
                $sql = "INSERT INTO transactions (account_id, amount, type, category1, category2, category3, description, date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$accountId, $amount, $type, '余额调整', '手动调整', '余额修正', $description, date('Y-m-d')]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollback();
            throw new Exception('调整账户余额失败: ' . $e->getMessage());
        }
    }
    
    public function updateTransaction($id, $accountId, $amount, $type, $category1, $category2, $category3, $description = '', $date = null) {
        try {
            $this->pdo->beginTransaction();
            
            // 获取原交易信息
            $oldTransaction = $this->getTransactionById($id);
            if (!$oldTransaction) {
                throw new Exception('交易不存在');
            }
            
            // 恢复原交易对账户余额的影响
            $oldBalanceChange = $oldTransaction['type'] === 'income' ? -$oldTransaction['amount'] : $oldTransaction['amount'];
            $sql = "UPDATE accounts SET balance = balance + ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$oldBalanceChange, $oldTransaction['account_id']]);
            
            // 更新交易记录
            if ($date === null) {
                $date = $oldTransaction['date'];
            }
            
            $sql = "UPDATE transactions SET 
                    account_id = ?, amount = ?, type = ?, 
                    category1 = ?, category2 = ?, category3 = ?, 
                    description = ?, date = ?
                    WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$accountId, $amount, $type, $category1, $category2, $category3, $description, $date, $id]);
            
            // 应用新交易对账户余额的影响
            $newBalanceChange = $type === 'income' ? $amount : -$amount;
            $sql = "UPDATE accounts SET balance = balance + ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$newBalanceChange, $accountId]);
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollback();
            throw new Exception('更新交易失败: ' . $e->getMessage());
        }
    }
    
    public function getCategories() {
        try {
            $categories = [
                'category2' => [],
                'category3' => []
            ];
            
            // 获取所有不重复的中类
            $sql = "SELECT DISTINCT category2 FROM transactions WHERE category2 != '' ORDER BY category2";
            $stmt = $this->pdo->query($sql);
            $categories['category2'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category2');
            
            // 获取所有不重复的小类
            $sql = "SELECT DISTINCT category3 FROM transactions WHERE category3 != '' ORDER BY category3";
            $stmt = $this->pdo->query($sql);
            $categories['category3'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category3');
            
            return $categories;
        } catch (PDOException $e) {
            throw new Exception('获取分类失败: ' . $e->getMessage());
        }
    }
    
    public function getCategoriesByType($category1 = null) {
        try {
            $categories = [
                'category2' => [],
                'category3' => []
            ];
            
            if ($category1) {
                // 根据大类获取相关的中类
                $sql = "SELECT DISTINCT category2 FROM transactions WHERE category1 = ? AND category2 != '' ORDER BY category2";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$category1]);
                $categories['category2'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category2');
                
                // 根据大类获取相关的小类
                $sql = "SELECT DISTINCT category3 FROM transactions WHERE category1 = ? AND category3 != '' ORDER BY category3";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$category1]);
                $categories['category3'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category3');
            } else {
                return $this->getCategories();
            }
            
            return $categories;
        } catch (PDOException $e) {
            throw new Exception('获取分类失败: ' . $e->getMessage());
        }
    }
    
    public function getAllCategories() {
        try {
            $categories = [
                'category1' => [],
                'category2' => [],
                'category3' => []
            ];
            
            // 获取所有不重复的大类
            $sql = "SELECT DISTINCT category1 FROM transactions WHERE category1 != '' ORDER BY category1";
            $stmt = $this->pdo->query($sql);
            $categories['category1'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category1');
            
            // 获取所有不重复的中类
            $sql = "SELECT DISTINCT category2 FROM transactions WHERE category2 != '' ORDER BY category2";
            $stmt = $this->pdo->query($sql);
            $categories['category2'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category2');
            
            // 获取所有不重复的小类
            $sql = "SELECT DISTINCT category3 FROM transactions WHERE category3 != '' ORDER BY category3";
            $stmt = $this->pdo->query($sql);
            $categories['category3'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category3');
            
            return $categories;
        } catch (PDOException $e) {
            throw new Exception('获取所有分类失败: ' . $e->getMessage());
        }
    }
    
    public function getCategoriesByParent($category1 = null, $category2 = null) {
        try {
            $categories = [
                'category2' => [],
                'category3' => []
            ];
            
            if ($category1) {
                // 根据大类获取相关的中类
                $sql = "SELECT DISTINCT category2 FROM transactions WHERE category1 = ? AND category2 != '' ORDER BY category2";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$category1]);
                $categories['category2'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category2');
                
                if ($category2) {
                    // 根据大类和中类获取相关的小类
                    $sql = "SELECT DISTINCT category3 FROM transactions WHERE category1 = ? AND category2 = ? AND category3 != '' ORDER BY category3";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$category1, $category2]);
                    $categories['category3'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category3');
                } else {
                    // 只根据大类获取相关的小类
                    $sql = "SELECT DISTINCT category3 FROM transactions WHERE category1 = ? AND category3 != '' ORDER BY category3";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$category1]);
                    $categories['category3'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category3');
                }
            } else {
                return $this->getAllCategories();
            }
            
            return $categories;
        } catch (PDOException $e) {
            throw new Exception('获取分类失败: ' . $e->getMessage());
        }
    }
}

?> 