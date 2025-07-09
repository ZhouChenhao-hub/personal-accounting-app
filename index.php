<?php
session_start();
require_once 'data.php';

// 设置缓存控制头，防止动态内容被缓存
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 硬编码的账号密码
define('LOGIN_USERNAME', 'admin');
define('LOGIN_PASSWORD', 'password');

// 检查登录状态
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// 登录验证
function authenticate($username, $password) {
    return $username === LOGIN_USERNAME && $password === LOGIN_PASSWORD;
}

$db = new AccountingDB();
$action = $_GET['action'] ?? 'dashboard';
// 如果没有指定action，默认为dashboard
if (empty($action)) {
    $action = 'dashboard';
}

// 处理登录和登出
if ($action == 'login' && $_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (authenticate($username, $password)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        header('Location: index.php?action=dashboard');
        exit;
    } else {
        $login_error = '用户名或密码错误';
    }
} elseif ($action == 'logout') {
    session_destroy();
    header('Location: index.php?action=login');
    exit;
}

// 处理AJAX请求
if ($action == 'get_categories') {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    header('Content-Type: application/json');
    $category1 = $_GET['category1'] ?? null;
    $categories = $db->getCategoriesByType($category1);
    echo json_encode($categories);
    exit;
}

if ($action == 'get_income_expense_trend') {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    header('Content-Type: application/json');
    $period = $_GET['period'] ?? 'month';
    $trendData = $db->getIncomeExpenseTrend($period);
    echo json_encode($trendData);
    exit;
}

if ($action == 'get_filter_categories') {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    header('Content-Type: application/json');
    $category1 = $_GET['category1'] ?? null;
    $category2 = $_GET['category2'] ?? null;
    $categories = $db->getCategoriesByParent($category1, $category2);
    echo json_encode($categories);
    exit;
}

if ($action == 'filter_transactions') {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    header('Content-Type: application/json');
    $keyword = $_GET['keyword'] ?? '';
    $accountId = $_GET['account_id'] ?? null;
    $type = $_GET['type'] ?? null;
    $category1 = $_GET['category1'] ?? null;
    $category2 = $_GET['category2'] ?? null;
    $category3 = $_GET['category3'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    
    $filteredTransactions = $db->searchTransactions($keyword, $accountId, $type, $category1, $category2, $category3, $dateFrom, $dateTo);
    echo json_encode($filteredTransactions);
    exit;
}

// 如果未登录且不是登录页面，重定向到登录页面
if (!isLoggedIn() && $action !== 'login') {
    $action = 'login';
}

// 处理表单提交
if ($_POST && isLoggedIn()) {
    switch ($_POST['action']) {
        case 'add_account':
            $db->addAccount($_POST['name'], $_POST['type']);
            header('Location: index.php?action=accounts&success=' . urlencode('账户添加成功'));
            exit;
        case 'add_transaction':
            $db->addTransaction(
                $_POST['account_id'],
                $_POST['amount'],
                $_POST['type'],
                $_POST['category1'],
                $_POST['category2'],
                $_POST['category3'] ?? '',
                $_POST['description'],
                $_POST['date']
            );
            // 设置更新标记
            $_SESSION['data_updated'] = true;
            break;
        case 'adjust_balance':
            $db->adjustAccountBalance(
                $_POST['account_id'],
                $_POST['new_balance'],
                $_POST['reason']
            );
            break;
        case 'update_transaction':
            $db->updateTransaction(
                $_POST['transaction_id'],
                $_POST['account_id'],
                $_POST['amount'],
                $_POST['type'],
                $_POST['category1'],
                $_POST['category2'],
                $_POST['category3'] ?? '',
                $_POST['description'],
                $_POST['date']
            );
            // 如果是AJAX请求，返回成功响应
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                http_response_code(200);
                echo json_encode(['success' => true]);
                exit;
            }
            header('Location: index.php?action=transactions');
            exit;
        case 'delete_transaction':
            $db->deleteTransaction($_POST['transaction_id']);
            $_SESSION['data_updated'] = true;
            header('Location: index.php?action=transactions');
            exit;
        case 'delete_account':
            try {
                $db->deleteAccount($_POST['account_id']);
                header('Location: index.php?action=accounts&success=' . urlencode('账户删除成功'));
            } catch (Exception $e) {
                // 如果删除失败，重定向并显示错误信息
                header('Location: index.php?action=accounts&error=' . urlencode($e->getMessage()));
            }
            exit;
    }
    header('Location: index.php?action=' . $action);
    exit;
}

// 只有登录后才获取数据
if (isLoggedIn()) {
$accounts = $db->getAccounts();
$transactions = $db->getTransactions();
$stats = $db->getStats();
    $categories = $db->getCategories();
    $allCategories = $db->getAllCategories();
    $incomeExpenseTrend = $db->getIncomeExpenseTrend('month');
}


?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人记账本</title>
    
    <!-- PWA Meta Tags -->
    <meta name="application-name" content="个人记账本">
    <meta name="apple-mobile-web-app-title" content="记账本">
    <meta name="description" content="简洁高效的个人财务管理系统">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="msapplication-navbutton-color" content="#0d6efd">
    <meta name="msapplication-TileColor" content="#0d6efd">
    <meta name="msapplication-starturl" content="/">
    
    <!-- PWA Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icon-512.png">
    <link rel="apple-touch-icon" sizes="192x192" href="icon-192.png">
    <link rel="apple-touch-icon" sizes="512x512" href="icon-512.png">
    <link rel="shortcut icon" href="icon-192.png">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <script src="assets/js/chart.min.js"></script>
    <style>
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
        }
        .card {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .income {
            color: #28a745;
        }
        .expense {
            color: #dc3545;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .fade-in {
            animation: fadeIn 0.2s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-dialog {
            max-width: 800px;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
                 @media (max-width: 768px) {
             .modal-dialog {
                 margin: 0.5rem;
                 max-width: none;
             }
         }
         .btn-outline-danger:hover {
             background-color: #dc3545;
             border-color: #dc3545;
             color: white;
         }
         .table th:last-child,
         .table td:last-child {
             width: 120px;
             text-align: center;
         }
         .form-control[list] {
             background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
             background-repeat: no-repeat;
             background-position: right 0.75rem center;
             background-size: 16px 12px;
             padding-right: 2.25rem;
         }
         /* 登录页面样式 */
         .login-container {
             min-height: 100vh;
             display: flex;
             align-items: center;
             justify-content: center;
         }
         .login-card {
             box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
             border-radius: 8px;
             overflow: hidden;
         }
         .login-card .card-header {
             background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
             color: white;
             border-bottom: none;
         }
         .dropdown-toggle::after {
             margin-left: 0.5em;
         }
         /* 登出按钮样式 */
         .nav-link.text-danger:hover {
             background-color: #dc3545 !important;
             color: white !important;
             border-color: #dc3545 !important;
         }
         /* 时间段选择器样式 */
         .btn-group .btn-check:checked + .btn-outline-secondary {
             background-color: #0d6efd;
             border-color: #0d6efd;
             color: white;
         }
         .btn-group .btn-outline-secondary:hover {
             background-color: #0d6efd;
             border-color: #0d6efd;
             color: white;
         }
         .card-header .btn-group-sm .btn {
             padding: 0.25rem 0.5rem;
             font-size: 0.875rem;
         }
         
         /* 筛选面板样式 */
         .card.bg-light {
             border: 1px solid #dee2e6;
             box-shadow: 0 1px 3px rgba(0,0,0,0.1);
         }
         
         .card.bg-light .card-body {
             padding: 1.5rem;
         }
         
         .card.bg-light .form-label {
             font-weight: 500;
             color: #495057;
         }
         
         .card.bg-light .form-control {
             border-color: #ced4da;
         }
         
         .card.bg-light .form-control:focus {
             border-color: #0d6efd;
             box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
         }
         
         #filter_status {
             border-left: 4px solid #0d6efd;
             background-color: #e7f3ff;
             border-color: #bee5eb;
             color: #0c5460;
         }
         
         @media (max-width: 768px) {
             .card.bg-light .row .col-md-3 {
                 margin-bottom: 1rem;
             }
         }
         
         /* PWA 相关样式 */
         .pwa-install-prompt {
             position: fixed;
             bottom: 20px;
             left: 50%;
             transform: translateX(-50%);
             background: #0d6efd;
             color: white;
             padding: 15px 20px;
             border-radius: 8px;
             box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
             z-index: 9999;
             display: none;
             max-width: 90%;
             text-align: center;
         }
         
         .pwa-install-prompt button {
             background: white;
             color: #0d6efd;
             border: none;
             padding: 8px 16px;
             border-radius: 4px;
             margin: 0 5px;
             cursor: pointer;
         }
         
         .pwa-install-prompt button:hover {
             background: #f8f9fa;
         }
         
         .offline-indicator {
             position: fixed;
             top: 10px;
             right: 10px;
             background: #dc3545;
             color: white;
             padding: 8px 12px;
             border-radius: 4px;
             font-size: 12px;
             z-index: 9999;
             display: none;
         }
         
         .offline-indicator.online {
             background: #28a745;
         }
         
         .pwa-status-bar {
             position: fixed;
             top: 0;
             left: 0;
             right: 0;
             height: 4px;
             background: #0d6efd;
             z-index: 10000;
             display: none;
         }
         
         .pwa-status-bar.installing {
             display: block;
             animation: pulse 1s infinite;
         }
         
         @keyframes pulse {
             0% { opacity: 1; }
             50% { opacity: 0.5; }
             100% { opacity: 1; }
         }
    </style>
</head>
<body>
    <!-- PWA 状态栏 -->
    <div class="pwa-status-bar" id="pwaStatusBar"></div>
    
    <!-- 离线状态指示器 -->
    <div class="offline-indicator" id="offlineIndicator">离线模式</div>
    
    <!-- PWA 安装提示 -->
    <div class="pwa-install-prompt" id="pwaInstallPrompt">
        <p>将记账本添加到主屏幕，获得更好的体验！</p>
        <button onclick="installPWA()">安装应用</button>
        <button onclick="dismissInstallPrompt()">稍后再说</button>
    </div>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                
                <?php if (isLoggedIn()): ?>
                <!-- 导航栏 -->
                <ul class="nav nav-pills nav-justified mb-4 mt-3" id="mainNav">
                    <li class="nav-item">
                        <a class="nav-link <?= $action == 'dashboard' || $action == '' ? 'active' : '' ?>" href="#" onclick="showTab('dashboard')">仪表盘</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $action == 'transactions' ? 'active' : '' ?>" href="#" onclick="showTab('transactions')">交易记录</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $action == 'accounts' ? 'active' : '' ?>" href="#" onclick="showTab('accounts')">账户管理</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $action == 'add' ? 'active' : '' ?>" href="#" onclick="showTab('add')">添加交易</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $action == 'adjust' ? 'active' : '' ?>" href="#" onclick="showTab('adjust')">余额调整</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="?action=logout" onclick="return confirm('确定要登出吗？')">登出</a>
                    </li>
                </ul>
                <?php endif; ?>

                <?php if ($action == 'login'): ?>
                <!-- 登录页面 -->
                <div class="text-center mb-4 mt-5">
                    <h1>💰 个人记账本</h1>
                    <p class="text-muted">简洁高效的个人财务管理系统</p>
                </div>
                <div class="row justify-content-center">
                    <div class="col-md-6 col-lg-4">
                        <div class="card login-card">
                            <div class="card-header text-center">
                                <h4>系统登录</h4>
                            </div>
                            <div class="card-body">
                                <?php if (isset($login_error)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?= $login_error ?>
                                </div>
                                <?php endif; ?>
                                <form method="POST" action="?action=login">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">用户名</label>
                                        <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">密码</label>
                                        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">登录</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>

                    <!-- 仪表盘 -->
                <div id="dashboard" class="tab-content <?= ($action == 'dashboard' || $action == '') ? 'active' : '' ?>">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title">总资产</h5>
                                    <h3 class="text-primary">¥<?= number_format($stats['total_balance'], 2) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title">本月收入</h5>
                                    <h3 class="income">¥<?= number_format($stats['monthly_income'], 2) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title">本月支出</h5>
                                    <h3 class="expense">¥<?= number_format($stats['monthly_expense'], 2) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title">本月净值</h5>
                                    <h3 class="<?= $stats['monthly_net'] >= 0 ? 'income' : 'expense' ?>">
                                        ¥<?= number_format($stats['monthly_net'], 2) ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 图表 -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>支出分类统计</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="expenseChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5>收支趋势</h5>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="trend-period" id="week" value="week" autocomplete="off">
                                        <label class="btn btn-outline-secondary" for="week">一周</label>
                                        
                                        <input type="radio" class="btn-check" name="trend-period" id="month" value="month" autocomplete="off" checked>
                                        <label class="btn btn-outline-secondary" for="month">一月</label>
                                        
                                        <input type="radio" class="btn-check" name="trend-period" id="year" value="year" autocomplete="off">
                                        <label class="btn btn-outline-secondary" for="year">一年</label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="incomeExpenseChart"></canvas>
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 交易记录 -->
                <div id="transactions" class="tab-content <?= $action == 'transactions' ? 'active' : '' ?>">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>交易记录</h5>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="forceRefreshTransactions()">
                                🔄 刷新数据
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- 筛选表单 -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">关键词搜索</label>
                                                        <input type="text" class="form-control" id="filter_keyword" placeholder="搜索描述或分类...">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">账户</label>
                                                        <select class="form-control" id="filter_account">
                                                            <option value="">全部账户</option>
                                                            <?php foreach ($accounts as $account): ?>
                                                            <option value="<?= $account['id'] ?>"><?= $account['name'] ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">类型</label>
                                                        <select class="form-control" id="filter_type">
                                                            <option value="">全部类型</option>
                                                            <option value="income">收入</option>
                                                            <option value="expense">支出</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">大类</label>
                                                        <select class="form-control" id="filter_category1">
                                                            <option value="">全部大类</option>
                                                            <?php foreach ($allCategories['category1'] as $cat1): ?>
                                                            <option value="<?= htmlspecialchars($cat1) ?>"><?= htmlspecialchars($cat1) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">中类</label>
                                                        <select class="form-control" id="filter_category2">
                                                            <option value="">全部中类</option>
                                                            <?php foreach ($allCategories['category2'] as $cat2): ?>
                                                            <option value="<?= htmlspecialchars($cat2) ?>"><?= htmlspecialchars($cat2) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">小类</label>
                                                        <select class="form-control" id="filter_category3">
                                                            <option value="">全部小类</option>
                                                            <?php foreach ($allCategories['category3'] as $cat3): ?>
                                                            <option value="<?= htmlspecialchars($cat3) ?>"><?= htmlspecialchars($cat3) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">开始日期</label>
                                                        <input type="date" class="form-control" id="filter_date_from">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">结束日期</label>
                                                        <input type="date" class="form-control" id="filter_date_to">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <button type="button" class="btn btn-primary" onclick="filterTransactions()">筛选</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- 筛选状态提示 -->
                            <div id="filter_status" class="alert alert-info" style="display: none;">
                                <strong>筛选结果：</strong> 共找到 <span id="filter_count">0</span> 条记录
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>日期</th>
                                            <th>账户</th>
                                            <th>类型</th>
                                            <th>分类</th>
                                            <th>金额</th>
                                            <th>描述</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactions_table_body">
                                        <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?= $transaction['date'] ?></td>
                                            <td><?= $transaction['account_name'] ?></td>
                                            <td>
                                                <span class="badge <?= $transaction['type'] == 'income' ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= $transaction['type'] == 'income' ? '收入' : '支出' ?>
                                                </span>
                                            </td>
                                            <td><?= $transaction['category1'] ?> > <?= $transaction['category2'] ?><?= !empty($transaction['category3']) ? ' > ' . $transaction['category3'] : '' ?></td>
                                            <td class="<?= $transaction['type'] == 'income' ? 'income' : 'expense' ?>">
                                                <?= $transaction['type'] == 'income' ? '+' : '-' ?>¥<?= number_format($transaction['amount'], 2) ?>
                                            </td>
                                            <td><?= $transaction['description'] ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editTransaction(<?= htmlspecialchars(json_encode($transaction, JSON_HEX_QUOT | JSON_HEX_APOS), ENT_QUOTES, 'UTF-8') ?>)">
                                                    编辑
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这笔交易吗？')">
                                                    <input type="hidden" name="action" value="delete_transaction">
                                                    <input type="hidden" name="transaction_id" value="<?= $transaction['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">删除</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- 账户管理 -->
                <div id="accounts" class="tab-content <?= $action == 'accounts' ? 'active' : '' ?>">
                    <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>错误：</strong> <?= htmlspecialchars($_GET['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>成功：</strong> <?= htmlspecialchars($_GET['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>账户列表</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>账户名称</th>
                                                    <th>类型</th>
                                                    <th>余额</th>
                                                    <th>操作</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($accounts as $account): ?>
                                                <tr>
                                                    <td><?= $account['name'] ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?= $account['type'] ?></span>
                                                    </td>
                                                    <td class="<?= $account['balance'] >= 0 ? 'income' : 'expense' ?>">
                                                        ¥<?= number_format($account['balance'], 2) ?>
                                                    </td>
                                                    <td>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirmDeleteAccount('<?= htmlspecialchars($account['name'], ENT_QUOTES) ?>', <?= $account['id'] ?>)">
                                                            <input type="hidden" name="action" value="delete_account">
                                                            <input type="hidden" name="account_id" value="<?= $account['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                删除
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>添加账户</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="add_account">
                                        <div class="mb-3">
                                            <label class="form-label">账户名称</label>
                                            <input type="text" class="form-control" name="name" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">账户类型</label>
                                            <select class="form-control" name="type" required>
                                                <option value="银行卡">银行卡</option>
                                                <option value="支付宝">支付宝</option>
                                                <option value="微信">微信</option>
                                                <option value="现金">现金</option>
                                                <option value="信用卡">信用卡</option>
                                                <option value="其他">其他</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary">添加账户</button>
                                    </form>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 添加交易 -->
                <div id="add" class="tab-content <?= $action == 'add' ? 'active' : '' ?>">
                    <div class="card">
                        <div class="card-header">
                            <h5>添加交易</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_transaction">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">账户</label>
                                            <select class="form-control" name="account_id" required>
                                                <option value="">选择账户</option>
                                                <?php foreach ($accounts as $account): ?>
                                                <option value="<?= $account['id'] ?>"><?= $account['name'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">类型</label>
                                            <select class="form-control" name="type" required>
                                                <option value="income">收入</option>
                                                <option value="expense" selected>支出</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">大类</label>
                                            <select class="form-control" name="category1" required>
                                                <option value="">选择大类</option>
                                                <option value="餐饮">餐饮</option>
                                                <option value="交通">交通</option>
                                                <option value="购物">购物</option>
                                                <option value="娱乐">娱乐</option>
                                                <option value="医疗">医疗</option>
                                                <option value="教育">教育</option>
                                                <option value="住房">住房</option>
                                                <option value="体育">体育</option>
                                                <option value="工资">工资</option>
                                                <option value="投资">投资</option>
                                                <option value="其他">其他</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">中类</label>
                                            <input type="text" class="form-control" name="category2" list="category2_list" placeholder="输入或选择中类" required>
                                            <datalist id="category2_list">
                                                <?php foreach ($categories['category2'] as $cat2): ?>
                                                <option value="<?= htmlspecialchars($cat2) ?>">
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">小类 <small class="text-muted">(可选)</small></label>
                                            <input type="text" class="form-control" name="category3" list="category3_list" placeholder="输入或选择小类">
                                            <datalist id="category3_list">
                                                <?php foreach ($categories['category3'] as $cat3): ?>
                                                <option value="<?= htmlspecialchars($cat3) ?>">
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">金额</label>
                                            <input type="number" class="form-control" name="amount" step="0.01" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">日期</label>
                                            <input type="date" class="form-control" name="date" value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">描述</label>
                                    <input type="text" class="form-control" name="description" placeholder="可选">
                                </div>
                                <button type="submit" class="btn btn-primary">添加交易</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 余额调整 -->
                <div id="adjust" class="tab-content <?= $action == 'adjust' ? 'active' : '' ?>">
                    <div class="card">
                        <div class="card-header">
                            <h5>余额调整</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <strong>注意：</strong> 余额调整会直接修改账户余额，并记录为一笔特殊交易。请谨慎操作。
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="adjust_balance">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">选择账户</label>
                                            <select class="form-control" name="account_id" id="account_select" required>
                                                <option value="">选择要调整的账户</option>
                                                <?php foreach ($accounts as $account): ?>
                                                <option value="<?= $account['id'] ?>" data-balance="<?= $account['balance'] ?>">
                                                    <?= $account['name'] ?> (当前余额: ¥<?= number_format($account['balance'], 2) ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">当前余额</label>
                                            <input type="text" class="form-control" id="current_balance" readonly placeholder="选择账户后显示">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">新余额</label>
                                            <input type="number" class="form-control" name="new_balance" step="0.01" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">调整原因</label>
                                            <input type="text" class="form-control" name="reason" placeholder="如：初始余额、银行对账、错误修正等" required>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-warning" onclick="return confirm('确定要调整账户余额吗？此操作将记录为一笔交易。')">调整余额</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- 编辑交易模态弹窗 -->
    <div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTransactionModalLabel">编辑交易</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editTransactionForm">
                        <input type="hidden" name="action" value="update_transaction">
                        <input type="hidden" name="transaction_id" id="edit_transaction_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">账户</label>
                                    <select class="form-control" name="account_id" id="edit_account_id" required>
                                        <option value="">选择账户</option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= $account['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">类型</label>
                                    <select class="form-control" name="type" id="edit_type" required>
                                        <option value="income">收入</option>
                                        <option value="expense">支出</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">大类</label>
                                    <select class="form-control" name="category1" id="edit_category1" required>
                                        <option value="">选择大类</option>
                                        <option value="餐饮">餐饮</option>
                                        <option value="交通">交通</option>
                                        <option value="购物">购物</option>
                                        <option value="娱乐">娱乐</option>
                                        <option value="医疗">医疗</option>
                                        <option value="教育">教育</option>
                                        <option value="住房">住房</option>
                                        <option value="体育">体育</option>
                                        <option value="工资">工资</option>
                                        <option value="投资">投资</option>
                                        <option value="余额调整">余额调整</option>
                                        <option value="其他">其他</option>
                                    </select>
                                </div>
                            </div>
                                                         <div class="col-md-4">
                                 <div class="mb-3">
                                     <label class="form-label">中类</label>
                                     <input type="text" class="form-control" name="category2" id="edit_category2" list="edit_category2_list" placeholder="输入或选择中类" required>
                                     <datalist id="edit_category2_list">
                                         <?php foreach ($categories['category2'] as $cat2): ?>
                                         <option value="<?= htmlspecialchars($cat2) ?>">
                                         <?php endforeach; ?>
                                     </datalist>
                                 </div>
                             </div>
                             <div class="col-md-4">
                                 <div class="mb-3">
                                     <label class="form-label">小类 <small class="text-muted">(可选)</small></label>
                                     <input type="text" class="form-control" name="category3" id="edit_category3" list="edit_category3_list" placeholder="输入或选择小类">
                                     <datalist id="edit_category3_list">
                                         <?php foreach ($categories['category3'] as $cat3): ?>
                                         <option value="<?= htmlspecialchars($cat3) ?>">
                                         <?php endforeach; ?>
                                     </datalist>
                                 </div>
                             </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">金额</label>
                                    <input type="number" class="form-control" name="amount" id="edit_amount" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">日期</label>
                                    <input type="date" class="form-control" name="date" id="edit_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">描述</label>
                            <input type="text" class="form-control" name="description" id="edit_description" placeholder="可选">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary" form="editTransactionForm">更新交易</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // 全局变量
        let chartsInitialized = false;
        let expenseChart = null;
        let incomeExpenseChart = null;
        
        // 标签页切换函数
        function showTab(tabName) {
            // 移除所有标签页的active类
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // 移除所有导航链接的active类
            const navLinks = document.querySelectorAll('#mainNav .nav-link');
            navLinks.forEach(link => {
                link.classList.remove('active');
            });
            
            // 显示目标标签页
            const targetTab = document.getElementById(tabName);
            if (targetTab) {
                targetTab.classList.add('active', 'fade-in');
                
                // 激活对应的导航链接
                const activeNavLink = document.querySelector(`#mainNav .nav-link[onclick="showTab('${tabName}')"]`);
                if (activeNavLink) {
                    activeNavLink.classList.add('active');
                }
                
                // 如果是仪表盘标签，初始化图表
                if (tabName === 'dashboard' && !chartsInitialized) {
                    initializeCharts();
                    chartsInitialized = true;
                }
                
                // 如果是余额调整标签，初始化相关功能
                if (tabName === 'adjust') {
                    initializeAdjustBalance();
                }
            }
        }
        
                // 初始化图表
        function initializeCharts() {
        // 支出分类图表
        const expenseData = <?= json_encode($db->getExpenseByCategory()) ?>;
        const expenseCtx = document.getElementById('expenseChart').getContext('2d');
            expenseChart = new Chart(expenseCtx, {
            type: 'doughnut',
            data: {
                labels: expenseData.map(item => item.category1),
                datasets: [{
                    data: expenseData.map(item => item.amount),
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                        '#FF6384',
                        '#C9CBCF'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // 收支趋势图表
        initializeIncomeExpenseChart('month');
        
        // 添加时间段选择器事件监听
        const periodRadios = document.querySelectorAll('input[name="trend-period"]');
        periodRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    updateIncomeExpenseChart(this.value);
                }
            });
        });
        }
        
        // 初始化收支趋势图表
        function initializeIncomeExpenseChart(period) {
            const incomeExpenseCtx = document.getElementById('incomeExpenseChart');
            if (!incomeExpenseCtx) return;
            
            // 如果图表已存在，先销毁
            if (incomeExpenseChart) {
                incomeExpenseChart.destroy();
            }
            
            // 创建新图表
            incomeExpenseChart = new Chart(incomeExpenseCtx.getContext('2d'), {
            type: 'bar',
            data: {
                    labels: [],
                datasets: [{
                        label: '收入',
                        data: [],
                        backgroundColor: '#28a745',
                        borderColor: '#28a745',
                        borderWidth: 1
                    }, {
                        label: '支出',
                        data: [],
                        backgroundColor: '#dc3545',
                        borderColor: '#dc3545',
                        borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                plugins: {
                    legend: {
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y;
                                    return `${label}: ¥${value.toFixed(2)}`;
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
            
            // 加载数据
            updateIncomeExpenseChart(period);
        }
        
        // 更新收支趋势图表
        function updateIncomeExpenseChart(period) {
            if (!incomeExpenseChart) return;
            
            // 显示加载状态
            const chart = incomeExpenseChart;
            
            fetch(`?action=get_income_expense_trend&period=${period}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        // 如果没有数据，显示空状态
                        chart.data.labels = ['无数据'];
                        chart.data.datasets[0].data = [0];
                        chart.data.datasets[1].data = [0];
                        chart.update();
                        return;
                    }
                    
                    // 格式化标签
                    const labels = data.map(item => {
                        if (period === 'week') {
                            // 显示日期，格式为 MM-DD
                            const date = new Date(item.period + 'T00:00:00');
                            return `${(date.getMonth() + 1).toString().padStart(2, '0')}-${date.getDate().toString().padStart(2, '0')}`;
                        } else if (period === 'month') {
                            // 显示日期，格式为 MM-DD
                            const date = new Date(item.period + 'T00:00:00');
                            return `${(date.getMonth() + 1).toString().padStart(2, '0')}-${date.getDate().toString().padStart(2, '0')}`;
                        } else {
                            // 年度显示月份，格式为 YYYY-MM
                            return item.period;
                        }
                    });
                    
                    // 更新图表数据
                    chart.data.labels = labels;
                    chart.data.datasets[0].data = data.map(item => parseFloat(item.income) || 0);
                    chart.data.datasets[1].data = data.map(item => parseFloat(item.expense) || 0);
                    chart.update();
                })
                .catch(error => {
                    console.error('获取收支趋势数据失败:', error);
                    // 错误状态显示
                    chart.data.labels = ['加载失败'];
                    chart.data.datasets[0].data = [0];
                    chart.data.datasets[1].data = [0];
                    chart.update();
                });
        }
        
        // 初始化余额调整功能
        function initializeAdjustBalance() {
            const accountSelect = document.getElementById('account_select');
            if (accountSelect) {
                accountSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const currentBalance = selectedOption.getAttribute('data-balance');
                    const currentBalanceField = document.getElementById('current_balance');
                    
                    if (currentBalance) {
                        currentBalanceField.value = '¥' + parseFloat(currentBalance).toFixed(2);
                    } else {
                        currentBalanceField.value = '';
                    }
                });
            }
        }
        
        // 确认删除账户函数
        function confirmDeleteAccount(accountName, accountId) {
            return confirm('确定要删除账户 "' + accountName + '" 吗？\n\n注意：如果该账户存在交易记录，删除将会失败。\n此操作不可撤销！');
        }
        
        // 编辑交易函数
        function editTransaction(transaction) {
            // 填充表单数据
            document.getElementById('edit_transaction_id').value = transaction.id;
            document.getElementById('edit_account_id').value = transaction.account_id;
            document.getElementById('edit_type').value = transaction.type;
            document.getElementById('edit_category1').value = transaction.category1;
            document.getElementById('edit_category2').value = transaction.category2;
            document.getElementById('edit_category3').value = transaction.category3 || '';
            document.getElementById('edit_amount').value = transaction.amount;
            document.getElementById('edit_date').value = transaction.date;
            document.getElementById('edit_description').value = transaction.description || '';
            
            // 显示模态弹窗
            const modal = new bootstrap.Modal(document.getElementById('editTransactionModal'));
            modal.show();
            
            // 根据当前大类更新中类和小类选项
            if (transaction.category1) {
                setTimeout(() => {
                    updateCategoryOptions(transaction.category1, true);
                }, 100); // 稍微延迟以确保弹窗完全显示
            }
            
            // 监听弹窗关闭事件，重置表单
            document.getElementById('editTransactionModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('editTransactionForm').reset();
            }, { once: true });
        }
        
        // 更新分类选项
        function updateCategoryOptions(category1, isEditForm = false) {
            if (!category1) return;
            
            const prefix = isEditForm ? 'edit_' : '';
            const category2List = document.getElementById(prefix + 'category2_list');
            const category3List = document.getElementById(prefix + 'category3_list');
            
            if (!category2List || !category3List) return;
            
            // 发送AJAX请求获取相关分类
            fetch(`?action=get_categories&category1=${encodeURIComponent(category1)}`)
                .then(response => response.json())
                .then(data => {
                    // 更新中类选项
                    category2List.innerHTML = '';
                    data.category2.forEach(cat2 => {
                        const option = document.createElement('option');
                        option.value = cat2;
                        category2List.appendChild(option);
                    });
                    
                    // 更新小类选项
                    category3List.innerHTML = '';
                    data.category3.forEach(cat3 => {
                        const option = document.createElement('option');
                        option.value = cat3;
                        category3List.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('获取分类失败:', error);
                });
        }
        
        // 初始化分类选择器
        function initializeCategorySelectors() {
            // 添加交易表单的大类选择器
            const addCategory1 = document.querySelector('#add select[name="category1"]');
            if (addCategory1) {
                addCategory1.addEventListener('change', function() {
                    updateCategoryOptions(this.value, false);
                });
            }
            
            // 编辑交易表单的大类选择器
            const editCategory1 = document.querySelector('#edit_category1');
            if (editCategory1) {
                editCategory1.addEventListener('change', function() {
                    updateCategoryOptions(this.value, true);
                });
            }
            
            // 筛选表单的分类选择器
            const filterCategory1 = document.getElementById('filter_category1');
            if (filterCategory1) {
                filterCategory1.addEventListener('change', function() {
                    updateFilterCategories(this.value, null);
                });
            }
            
            const filterCategory2 = document.getElementById('filter_category2');
            if (filterCategory2) {
                filterCategory2.addEventListener('change', function() {
                    const category1 = document.getElementById('filter_category1').value;
                    updateFilterCategories(category1, this.value);
                });
            }
        }
        
        // 更新筛选分类选项
        function updateFilterCategories(category1, category2) {
            const category2Select = document.getElementById('filter_category2');
            const category3Select = document.getElementById('filter_category3');
            
            if (!category2Select || !category3Select) return;
            
            let url = '?action=get_filter_categories';
            if (category1) url += '&category1=' + encodeURIComponent(category1);
            if (category2) url += '&category2=' + encodeURIComponent(category2);
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    // 更新中类选项
                    if (category1) {
                        const currentCategory2 = category2Select.value;
                        category2Select.innerHTML = '<option value="">全部中类</option>';
                        data.category2.forEach(cat2 => {
                            const option = document.createElement('option');
                            option.value = cat2;
                            option.textContent = cat2;
                            if (cat2 === currentCategory2) {
                                option.selected = true;
                            }
                            category2Select.appendChild(option);
                        });
                    }
                    
                    // 更新小类选项
                    const currentCategory3 = category3Select.value;
                    category3Select.innerHTML = '<option value="">全部小类</option>';
                    data.category3.forEach(cat3 => {
                        const option = document.createElement('option');
                        option.value = cat3;
                        option.textContent = cat3;
                        if (cat3 === currentCategory3) {
                            option.selected = true;
                        }
                        category3Select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('获取筛选分类失败:', error);
                });
        }
        
        // 执行筛选
        function filterTransactions() {
            const keyword = document.getElementById('filter_keyword').value;
            const accountId = document.getElementById('filter_account').value;
            const type = document.getElementById('filter_type').value;
            const category1 = document.getElementById('filter_category1').value;
            const category2 = document.getElementById('filter_category2').value;
            const category3 = document.getElementById('filter_category3').value;
            const dateFrom = document.getElementById('filter_date_from').value;
            const dateTo = document.getElementById('filter_date_to').value;
            
            // 构建查询参数
            const params = new URLSearchParams();
            params.append('action', 'filter_transactions');
            if (keyword) params.append('keyword', keyword);
            if (accountId) params.append('account_id', accountId);
            if (type) params.append('type', type);
            if (category1) params.append('category1', category1);
            if (category2) params.append('category2', category2);
            if (category3) params.append('category3', category3);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            // 显示加载状态
            const tableBody = document.getElementById('transactions_table_body');
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center">筛选中...</td></tr>';
            
            fetch('?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    updateTransactionTable(data);
                    
                    // 显示筛选状态
                    const filterStatus = document.getElementById('filter_status');
                    const filterCount = document.getElementById('filter_count');
                    filterCount.textContent = data.length;
                    filterStatus.style.display = 'block';
                })
                .catch(error => {
                    console.error('筛选失败:', error);
                    tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">筛选失败，请重试</td></tr>';
                });
        }
        

        
        // 更新交易表格
        function updateTransactionTable(transactions) {
            const tableBody = document.getElementById('transactions_table_body');
            
            if (transactions.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" class="text-center">没有找到符合条件的交易记录</td></tr>';
                return;
            }
            
            let html = '';
            transactions.forEach(transaction => {
                const typeClass = transaction.type === 'income' ? 'bg-success' : 'bg-danger';
                const typeText = transaction.type === 'income' ? '收入' : '支出';
                const amountClass = transaction.type === 'income' ? 'income' : 'expense';
                const amountPrefix = transaction.type === 'income' ? '+' : '-';
                const category3 = transaction.category3 ? ' > ' + transaction.category3 : '';
                
                html += `
                    <tr>
                        <td>${transaction.date}</td>
                        <td>${transaction.account_name}</td>
                        <td>
                            <span class="badge ${typeClass}">${typeText}</span>
                        </td>
                        <td>${transaction.category1} > ${transaction.category2}${category3}</td>
                        <td class="${amountClass}">
                            ${amountPrefix}¥${parseFloat(transaction.amount).toFixed(2)}
                        </td>
                        <td>${transaction.description || ''}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="editTransaction(${JSON.stringify(transaction).replace(/'/g, '\\\'').replace(/"/g, '&quot;')})">
                                编辑
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这笔交易吗？')">
                                <input type="hidden" name="action" value="delete_transaction">
                                <input type="hidden" name="transaction_id" value="${transaction.id}">
                                <button type="submit" class="btn btn-sm btn-outline-danger">删除</button>
                            </form>
                        </td>
                    </tr>
                `;
            });
            
            tableBody.innerHTML = html;
        }
        
        // PWA 相关全局变量
        let deferredPrompt;
        let swRegistration;
        
        // 强制刷新交易记录数据
        function forceRefreshTransactions() {
            // 清除Service Worker缓存
            if ('caches' in window) {
                caches.keys().then(cacheNames => {
                    cacheNames.forEach(cacheName => {
                        if (cacheName.includes('accounting-app')) {
                            caches.open(cacheName).then(cache => {
                                cache.keys().then(requests => {
                                    requests.forEach(request => {
                                        if (request.url.includes('index.php')) {
                                            cache.delete(request);
                                        }
                                    });
                                });
                            });
                        }
                    });
                });
            }
            
            // 强制从服务器重新加载页面
            window.location.reload(true);
        }
        
        // 检查是否需要刷新数据
        function checkForDataUpdates() {
            const lastUpdate = localStorage.getItem('lastTransactionUpdate');
            const currentTime = Date.now();
            
            // 如果超过30秒没有更新，检查是否有新数据
            if (!lastUpdate || currentTime - parseInt(lastUpdate) > 30000) {
                localStorage.setItem('lastTransactionUpdate', currentTime.toString());
                // 可以在这里添加检查逻辑
            }
        }
        
        // PWA 功能函数
        function initializePWA() {
            // 注册 Service Worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('./sw.js')
                    .then(registration => {
                        console.log('Service Worker registered:', registration);
                        swRegistration = registration;
                        
                        // 检查更新
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    showUpdateAvailable();
                                }
                            });
                        });
                    })
                    .catch(error => {
                        console.error('Service Worker registration failed:', error);
                    });
            }
            
            // 监听 PWA 安装事件
            window.addEventListener('beforeinstallprompt', (e) => {
                console.log('PWA install prompt triggered');
                e.preventDefault();
                deferredPrompt = e;
                
                // 延迟显示安装提示
                setTimeout(() => {
                    if (!isPWAInstalled()) {
                        showInstallPrompt();
                    }
                }, 10000); // 10秒后显示
            });
            
            // 监听 PWA 安装完成事件
            window.addEventListener('appinstalled', (evt) => {
                console.log('PWA installed successfully');
                hideInstallPrompt();
                localStorage.setItem('pwa-installed', 'true');
                showToast('应用已成功安装到主屏幕！', 'success');
            });
            
            // 监听网络状态变化
            window.addEventListener('online', () => {
                updateOnlineStatus(true);
            });
            
            window.addEventListener('offline', () => {
                updateOnlineStatus(false);
            });
            
            // 初始化在线状态
            updateOnlineStatus(navigator.onLine);
        }
        
        function isPWAInstalled() {
            return localStorage.getItem('pwa-installed') === 'true' || 
                   window.matchMedia('(display-mode: standalone)').matches;
        }
        
        function showInstallPrompt() {
            if (deferredPrompt && !isPWAInstalled()) {
                document.getElementById('pwaInstallPrompt').style.display = 'block';
            }
        }
        
        function hideInstallPrompt() {
            document.getElementById('pwaInstallPrompt').style.display = 'none';
        }
        
        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted PWA installation');
                    } else {
                        console.log('User dismissed PWA installation');
                    }
                    deferredPrompt = null;
                    hideInstallPrompt();
                });
            }
        }
        
        function dismissInstallPrompt() {
            hideInstallPrompt();
            // 24小时后再次显示
            setTimeout(() => {
                if (!isPWAInstalled()) {
                    showInstallPrompt();
                }
            }, 24 * 60 * 60 * 1000);
        }
        
        function updateOnlineStatus(isOnline) {
            const indicator = document.getElementById('offlineIndicator');
            if (isOnline) {
                indicator.textContent = '在线';
                indicator.className = 'offline-indicator online';
                indicator.style.display = 'block';
                setTimeout(() => {
                    indicator.style.display = 'none';
                }, 3000);
            } else {
                indicator.textContent = '离线模式';
                indicator.className = 'offline-indicator';
                indicator.style.display = 'block';
            }
        }
        
        function showUpdateAvailable() {
            const updateToast = document.createElement('div');
            updateToast.className = 'toast align-items-center text-white bg-info border-0';
            updateToast.setAttribute('role', 'alert');
            updateToast.setAttribute('aria-live', 'assertive');
            updateToast.setAttribute('aria-atomic', 'true');
            updateToast.style.position = 'fixed';
            updateToast.style.top = '20px';
            updateToast.style.right = '20px';
            updateToast.style.zIndex = '9999';
            updateToast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        应用有新版本可用！
                        <button class="btn btn-sm btn-light ms-2" onclick="updateApp()">更新</button>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(updateToast);
            
            const bsToast = new bootstrap.Toast(updateToast, { delay: 10000 });
            bsToast.show();
        }
        
        function updateApp() {
            if (swRegistration && swRegistration.waiting) {
                swRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
                window.location.reload();
            }
        }
        
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            
            const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
            bsToast.show();
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 3500);
        }
        
        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化 PWA 功能
            initializePWA();
            
            // 检查数据是否已更新
            <?php if (isset($_SESSION['data_updated']) && $_SESSION['data_updated']): ?>
            localStorage.setItem('lastTransactionUpdate', Date.now().toString());
            <?php unset($_SESSION['data_updated']); ?>
            <?php endif; ?>
            
            // 定期检查数据更新
            checkForDataUpdates();
            
            <?php if (isLoggedIn()): ?>
            // 如果当前是仪表盘页面，初始化图表
            <?php if ($action == 'dashboard' || $action == ''): ?>
            initializeCharts();
            chartsInitialized = true;
        <?php endif; ?>
            
            // 如果当前是余额调整页面，初始化相关功能
            <?php if ($action == 'adjust'): ?>
            initializeAdjustBalance();
            <?php endif; ?>
            
            // 初始化分类选择器
            initializeCategorySelectors();
            
            // 如果当前是交易记录页面，初始化筛选功能
            <?php if ($action == 'transactions'): ?>
            // 为筛选输入框添加回车事件
            const filterInputs = document.querySelectorAll('#filter_keyword, #filter_date_from, #filter_date_to');
            filterInputs.forEach(input => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        filterTransactions();
                    }
                });
            });
            <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($action == 'login'): ?>
            // 登录页面：自动聚焦到用户名输入框
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.focus();
            }
            
            // 登录表单提交时的加载状态
            const loginForm = document.querySelector('form[action="?action=login"]');
            if (loginForm) {
                loginForm.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.textContent = '登录中...';
                    submitBtn.disabled = true;
                });
            }
            <?php endif; ?>
            
            // 自动隐藏警告信息
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert-success, .alert-danger');
                alerts.forEach(function(alert) {
                    if (alert.classList.contains('show')) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                });
            }, 5000); // 5秒后自动隐藏
            
            <?php if (isLoggedIn()): ?>
            // 处理编辑交易表单提交
            const editForm = document.getElementById('editTransactionForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // 显示加载状态
                    const submitBtn = document.querySelector('button[form="editTransactionForm"]');
                    const originalText = submitBtn.textContent;
                    submitBtn.textContent = '更新中...';
                    submitBtn.disabled = true;
                    
                    // 提交表单数据
                    const formData = new FormData(editForm);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => {
                        if (response.ok) {
                            return response.json();
                        } else {
                            throw new Error('更新失败');
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            // 关闭模态弹窗
                            const modal = bootstrap.Modal.getInstance(document.getElementById('editTransactionModal'));
                            modal.hide();
                            
                            // 显示成功提示
                            const toast = document.createElement('div');
                            toast.className = 'toast align-items-center text-white bg-success border-0';
                            toast.setAttribute('role', 'alert');
                            toast.setAttribute('aria-live', 'assertive');
                            toast.setAttribute('aria-atomic', 'true');
                            toast.style.position = 'fixed';
                            toast.style.top = '20px';
                            toast.style.right = '20px';
                            toast.style.zIndex = '9999';
                            toast.innerHTML = `
                                <div class="d-flex">
                                    <div class="toast-body">
                                        交易更新成功！
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                                </div>
                            `;
                            document.body.appendChild(toast);
                            
                            const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
                            bsToast.show();
                            
                            // 3秒后移除toast元素
                            setTimeout(() => {
                                if (toast.parentNode) {
                                    toast.parentNode.removeChild(toast);
                                }
                            }, 3500);
                            
                            // 记录数据更新时间
                            localStorage.setItem('lastTransactionUpdate', Date.now().toString());
                            
                            // 刷新页面数据
                            setTimeout(() => forceRefreshTransactions(), 1000);
                        } else {
                            // 显示错误提示
                            const errorToast = document.createElement('div');
                            errorToast.className = 'toast align-items-center text-white bg-danger border-0';
                            errorToast.setAttribute('role', 'alert');
                            errorToast.setAttribute('aria-live', 'assertive');
                            errorToast.setAttribute('aria-atomic', 'true');
                            errorToast.style.position = 'fixed';
                            errorToast.style.top = '20px';
                            errorToast.style.right = '20px';
                            errorToast.style.zIndex = '9999';
                            errorToast.innerHTML = `
                                <div class="d-flex">
                                    <div class="toast-body">
                                        更新失败，请重试
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                                </div>
                            `;
                            document.body.appendChild(errorToast);
                            
                            const bsErrorToast = new bootstrap.Toast(errorToast, { delay: 3000 });
                            bsErrorToast.show();
                            
                            // 3秒后移除toast元素
                            setTimeout(() => {
                                if (errorToast.parentNode) {
                                    errorToast.parentNode.removeChild(errorToast);
                                }
                            }, 3500);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // 显示错误提示
                        const errorToast = document.createElement('div');
                        errorToast.className = 'toast align-items-center text-white bg-danger border-0';
                        errorToast.setAttribute('role', 'alert');
                        errorToast.setAttribute('aria-live', 'assertive');
                        errorToast.setAttribute('aria-atomic', 'true');
                        errorToast.style.position = 'fixed';
                        errorToast.style.top = '20px';
                        errorToast.style.right = '20px';
                        errorToast.style.zIndex = '9999';
                        errorToast.innerHTML = `
                            <div class="d-flex">
                                <div class="toast-body">
                                    更新失败，请重试
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        `;
                        document.body.appendChild(errorToast);
                        
                        const bsErrorToast = new bootstrap.Toast(errorToast, { delay: 3000 });
                        bsErrorToast.show();
                        
                        // 3秒后移除toast元素
                        setTimeout(() => {
                            if (errorToast.parentNode) {
                                errorToast.parentNode.removeChild(errorToast);
                            }
                        }, 3500);
                    })
                    .finally(() => {
                        // 恢复按钮状态
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    });
                });
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>
