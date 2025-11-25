<?php
// ===== SETUP & CONFIG =====
date_default_timezone_set('Asia/Jakarta');
session_start();

// 1. SETUP TEMA (LOGIKA DARK MODE TANPA JS)
// Jika session theme belum ada, set default ke 'light'
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}

// File config penyimpanan user
define('USERS_FILE', 'users.json');

// ===== USER AUTHENTICATION CLASS =====
class UserAuth {
    private string $usersFile;
    
    public function __construct(string $usersFile) {
        $this->usersFile = $usersFile;
        if (!file_exists($usersFile)) {
            file_put_contents($usersFile, json_encode([]));
        }
    }
    
    private function getUsers(): array {
        return json_decode(file_get_contents($this->usersFile), true) ?? [];
    }
    
    private function saveUsers(array $users): void {
        file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT));
    }
    
    public function register(string $username, string $password): array {
        if (strlen($username) < 3) return ['success' => false, 'message' => 'Username minimal 3 karakter'];
        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) return ['success' => false, 'message' => 'Username hanya huruf & angka'];
        if (strlen($password) < 8) return ['success' => false, 'message' => 'Password minimal 8 karakter'];
        
        $users = $this->getUsers();
        foreach ($users as $user) {
            if ($user['username'] === $username) return ['success' => false, 'message' => 'Username sudah terdaftar'];
        }
        
        $users[] = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->saveUsers($users);
        return ['success' => true, 'message' => 'Registrasi berhasil!'];
    }
    
    public function login(string $username, string $password): array {
        $users = $this->getUsers();
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                if (password_verify($password, $user['password'])) {
                    return ['success' => true, 'username' => $username];
                } else {
                    return ['success' => false, 'message' => 'Password salah'];
                }
            }
        }
        return ['success' => false, 'message' => 'Username tidak ditemukan'];
    }
}

// ===== AUTH ACTIONS & THEME ACTIONS =====
$auth = new UserAuth(USERS_FILE);
$errorMessage = '';
$successMessage = '';

// Handle Semua POST Request
if (isset($_POST['action'])) {
    
    // LOGIKA GANTI TEMA (Server Side Toggle)
    if ($_POST['action'] === 'toggle_theme') {
        $_SESSION['theme'] = ($_SESSION['theme'] === 'light') ? 'dark' : 'light';
        // Redirect agar tidak resubmit form saat refresh
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Register
    if ($_POST['action'] === 'register') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if ($password !== $confirmPassword) {
            $errorMessage = 'Password tidak sama';
        } else {
            $result = $auth->register($username, $password);
            if ($result['success']) {
                $_SESSION['user'] = $username;
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $errorMessage = $result['message'];
            }
        }
    } 
    // Login
    elseif ($_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $result = $auth->login($username, $password);
        if ($result['success']) {
            $_SESSION['user'] = $result['username'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $errorMessage = $result['message'];
        }
    } 
    // Logout
    elseif ($_POST['action'] === 'logout') {
        // Hapus user tapi pertahankan tema
        $currentTheme = $_SESSION['theme']; 
        session_destroy();
        session_start();
        $_SESSION['theme'] = $currentTheme;
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

$currentUser = $_SESSION['user'] ?? null;
$databaseFile = $currentUser ? 'data_' . $currentUser . '.json' : null;

// ===== HABIT CLASSES =====
class Habit {
    protected string $name;
    protected string $id;
    public function __construct(string $name, ?string $id = null) {
        $this->name = $name;
        $this->id = $id ?? uniqid();
    }
    public function getName(): string { return $this->name; }
    public function getId(): string { return $this->id; }
}

class TrackerHabit extends Habit {
    private array $completedDates;
    public function __construct(string $name, ?string $id = null, array $completedDates = []) {
        parent::__construct($name, $id);
        $this->completedDates = $completedDates;
    }
    public function isCompletedOn(string $date): bool {
        return in_array($date, $this->completedDates);
    }
    public function toggleDate(string $date): void {
        if ($this->isCompletedOn($date)) {
            $this->completedDates = array_diff($this->completedDates, [$date]);
        } else {
            $this->completedDates[] = $date;
        }
        $this->completedDates = array_values($this->completedDates);
    }
    public function getCurrentStreak(): int {
        $streak = 0;
        $checkDate = new DateTime();
        while (true) {
            $dateStr = $checkDate->format('Y-m-d');
            if ($this->isCompletedOn($dateStr)) {
                $streak++;
                $checkDate->modify('-1 day');
            } else {
                if ($dateStr === date('Y-m-d')) {
                    $checkDate->modify('-1 day');
                    continue;
                }
                break;
            }
        }
        return $streak;
    }
    public function getTotalCompletions(): int { return count($this->completedDates); }
    public function toArray(): array {
        return ['id' => $this->id, 'name' => $this->name, 'completed_dates' => $this->completedDates];
    }
}

class AchievementManager {
    public static function getBadges(int $streak, int $total): array {
        $badges = [];
        if ($total >= 1) $badges[] = ['icon' => '🌱', 'title' => 'First Steps', 'desc' => 'Start Langkah Pertamamu'];
        if ($streak >= 3) $badges[] = ['icon' => '🔥', 'title' => 'Fire Streak', 'desc' => 'Kamu sudah Konsisten 3 hari berturut-turut!'];
        if ($streak >= 7) $badges[] = ['icon' => '👑', 'title' => 'Habit Hero', 'desc' => 'Kamu Sudah Konsisten Selama Seminggu penuh!'];
        if ($streak >= 14) $badges[] = ['icon' => '⚡', 'title' => 'Unstoppable Person', 'desc' => 'SELAMAT KAMU SUDAH KONSISTEN SELAMA 2 MINGGU!'];
        if ($total >= 30) $badges[] = ['icon' => '💎', 'title' => 'Master', 'desc' => 'WOWW KONSISTEN 30 HARI'];
        if ($total >= 50) $badges[] = ['icon' => '🏆', 'title' => 'World Champion', 'desc' => 'ANDA ADALAH JUARA KARENA KONSISTEN SELAMA 50 HARII!'];
        return $badges;
    }
}

class JsonLibrary {
    private string $filePath;
    public function __construct(string $filePath) {
        $this->filePath = $filePath;
        if (!file_exists($filePath)) file_put_contents($filePath, json_encode([]));
    }
    public function loadData(): array {
        if (!file_exists($this->filePath)) return [];
        $data = json_decode(file_get_contents($this->filePath), true);
        $objects = [];
        if (is_array($data)) {
            foreach ($data as $item) {
                $dates = $item['completed_dates'] ?? [];
                $objects[] = new TrackerHabit($item['name'], $item['id'], $dates);
            }
        }
        return $objects;
    }
    public function saveData(array $habits): void {
        $data = array_map(fn($h) => $h->toArray(), $habits);
        file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT));
    }
}

// ===== CONTROLLER LOGIC =====
$habits = [];
$totalStreak = 0;
$totalCompletions = 0;
$globalBadges = [];
$periodDates = [];
$today = date('Y-m-d');
$mondayStr = date('Y-m-d', strtotime('monday this week'));

for ($i = 0; $i < 7; $i++) {
    $periodDates[] = date('Y-m-d', strtotime("$mondayStr +$i days"));
}

if ($currentUser) {
    $library = new JsonLibrary($databaseFile);
    $habits = $library->loadData();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add' && !empty(trim($_POST['habit_name']))) {
            $habits[] = new TrackerHabit(trim($_POST['habit_name']));
            $library->saveData($habits);
        }
        if ($action === 'toggle_date') {
            if ($_POST['date'] <= $today) {
                foreach ($habits as $habit) {
                    if ($habit->getId() === $_POST['id']) {
                        $habit->toggleDate($_POST['date']);
                        break;
                    }
                }
                $library->saveData($habits);
            }
        }
        if ($action === 'delete') {
            $habits = array_filter($habits, fn($h) => $h->getId() !== $_POST['id']);
            $library->saveData($habits);
        }
        
        // Redirect setelah aksi habit agar bersih (Post-Redirect-Get pattern)
        // Kita exclude logout/toggle_theme di sini karena sudah dihandle di atas
        if ($action !== 'logout' && $action !== 'toggle_theme') {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    
    foreach($habits as $h) {
        $totalStreak += $h->getCurrentStreak();
        $totalCompletions += $h->getTotalCompletions();
    }
    $maxStreak = empty($habits) ? 0 : max(array_map(fn($h)=>$h->getCurrentStreak(), $habits));
    $globalBadges = AchievementManager::getBadges($maxStreak, $totalCompletions);
}

$showRegister = isset($_GET['register']);
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?php echo $_SESSION['theme']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Habits Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root 
        {
            /* Light Theme — Calm Blue Neutral */
            --bg-body: #F5F7FA;         /* biru-putih lembut */
            --bg-card: #FFFFFF;         /* putih lembut (bukan putih keras) */
            --text-main: #374B63;       /* navy-gray lembut */
            --text-muted: #77859A;      /* abu kebiruan */
            
            --primary: #6C7AE0;         /* soft indigo (tidak terlalu tajam) */
            --primary-light: #EEF1FF;
            
            --accent: #F5B453;          /* orange lembut */
            --border: #E1E6EF;          /* abu kebiruan tipis */
            
            --success: #4BBF8C;         /* green calm */
            --error: #EA6A6A;           /* red soft */
            
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            --shadow-hover: 0 10px 25px rgba(108, 122, 224, 0.18);
            
            --radius: 24px;
            --radius-sm: 12px;
            --progress-bg: #3f4e86;     /* biru gelap lembut */
            --pattern-color: #AEB7C8;   /* warna grid dibuat lebih soft */
            --texture-shadow: rgba(0, 0, 0, 0.04);
        }
        
        [data-theme="dark"] 
        {
            --bg-body: #121923;         /* biru gelap */
            --bg-card: #1C2532;         /* dark-blue soft */
            --text-main: #E7ECF3;       /* putih ke-biru-an lembut */
            --text-muted: #9AA8B9;      /* soft blue-gray */
            --primary: #7D8CFF;         /* indigo lembut */
            --primary-light: #2D3357;
            
            --accent: #F2C063;          /* gold lembut */
            --border: #2A3342;
            
            --success: #4FD4A7;
            --error: #F28A8A;
            --shadow: 0 4px 20px rgba(0,0,0,0.25);
            --shadow-hover: 0 10px 25px rgba(0,0,0,0.35);
            --progress-bg: #2E3646;
            
            --pattern-color: #2E3646;   /* grid tidak terlalu keras */
            --texture-shadow: rgba(255,255,255,0.06);
        }
        
        * {
            box-sizing: border-box;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            /* --- TEXTURED BACKGROUND (Enhancement) --- */
            background-image: 
                linear-gradient(var(--pattern-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--pattern-color) 1px, transparent 1px),
                radial-gradient(circle at 40% 40%, var(--texture-shadow), transparent 60%);
            background-size: 
                22px 22px,   /* grid spacing */
                22px 22px,
                100% 100%;
            box-shadow: inset 0 0 80px var(--texture-shadow);
            /* ------------------------------------------------ */
            color: var(--text-main);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            min-height: 100vh;
        }
        
        .app-container {
            max-width: 850px;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 25px;
            position: relative;
            z-index: 1;
        }
        
        /* HEADER & CONTROLS */
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .logo-area h1 { margin: 0; font-size: 24px; font-weight: 800; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
        .logo-area p { margin: 5px 0 0; font-size: 13px; color: var(--text-muted); font-weight: 500; }
        
        .header-card {
            background: var(--bg-card);
            padding: 20px 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 10px;
        }
        
        .header-card h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
            background: linear-gradient(90deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            color: #333;
            -webkit-text-fill-color: transparent;
        }
        .emoji {
            color: initial !important;
            background: none !important;
            -webkit-text-fill-color: initial !important;
            filter: none !important;
        }
        
        .header-card h1 .emoji {
            -webkit-text-fill-color: initial !important;
        }
        
        .header-card p {
            margin: 5px 0 0;
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 600;
        }
        
        .controls { display: flex; gap: 10px; align-items: center; }
        
        .theme-toggle { 
            background: var(--bg-card); 
            border: 1px solid var(--border); 
            width: 40px; height: 40px; 
            border-radius: 50%; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 18px; 
            color: var(--text-main); 
            box-shadow: var(--shadow); 
        }
        
        .logout-btn { background: rgba(239, 68, 68, 0.1); color: var(--error); border: none; padding: 10px 20px; border-radius: 50px; font-weight: 700; font-size: 12px; cursor: pointer; transition: 0.2s; backdrop-filter: blur(5px);}
        .logout-btn:hover { background: var(--error); color: white; }
        
        /* CARDS & INPUTS */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        
        .input-group { position: relative; display: flex; width: 100%; gap: 10px; }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid var(--border);
            background: var(--bg-body);
            color: var(--text-main);
            border-radius: var(--radius-sm);
            outline: none;
            font-size: 15px;
            font-family: inherit;
            font-weight: 500;
        }
        input:focus { border-color: var(--primary); background: var(--bg-card); }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px 30px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            cursor: pointer;
            font-size: 15px;
            white-space: nowrap;
            transition: transform 0.1s, box-shadow 0.2s;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); }
        .btn-primary:active { transform: translateY(0); }
        
        /* HABIT TABLE */
        .table-wrapper { overflow-x: auto; margin: 0 -25px; padding: 0 25px; padding-bottom: 10px;}
        .habit-table { width: 100%; border-collapse: separate; border-spacing: 0 15px; }
        
        .th-day { text-align: center; min-width: 50px; padding-bottom: 10px; }
        .date-capsule { display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .day-name { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); }
        .day-num { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 13px; font-weight: 700; color: var(--text-main); }
        .current-day .day-num { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        
        .habit-row td { vertical-align: middle; }
        
        /* HABIT NAME CELL & PROGRESS BAR */
        .habit-info {
            position: sticky; left: 0; z-index: 10;
            background: var(--bg-card);
            padding: 15px;
            border-radius: var(--radius-sm);
            box-shadow: 4px 0 15px rgba(0,0,0,0.02);
            border: 1px solid var(--border);
            min-width: 180px;
        }
        .habit-title { font-weight: 700; font-size: 15px; display: flex; justify-content: space-between; align-items: center;}
        .habit-meta { font-size: 11px; color: var(--text-muted); margin-top: 5px; display: flex; align-items: center; gap: 8px;}
        
        .progress-track { height: 4px; background: var(--progress-bg); border-radius: 2px; width: 100%; margin-top: 10px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--accent)); border-radius: 2px; transition: width 0.5s ease-out; }
        
        /* CHECKBOXES */
        .check-wrapper { text-align: center; }
        .btn-check {
            width: 38px; height: 38px;
            border-radius: 12px; border: 2px solid var(--border);
            background: var(--bg-body);
            cursor: pointer; color: transparent;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 18px; transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .btn-check:hover { border-color: var(--primary); }
        .is-checked .btn-check {
            background: var(--success);
            border-color: var(--success);
            color: white;
            transform: scale(1.05);
        }
        .future-box { width: 38px; height: 38px; border-radius: 12px; background: var(--border); opacity: 0.3; margin: auto; }
        
        .btn-delete { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px; padding: 0; opacity: 0.6; transition: 0.2s;}
        .btn-delete:hover { color: var(--error); opacity: 1; }
        
        /* STATS & BADGES */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px;}
        .stat-card { background: var(--bg-card); border-radius: var(--radius-sm); padding: 15px; border: 1px solid var(--border); text-align: center; box-shadow: var(--shadow);}
        .stat-val { font-size: 24px; font-weight: 800; color: var(--primary); }
        .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }
        
        .badges-scroll { display: flex; gap: 15px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: none; }
        .badges-scroll::-webkit-scrollbar { display: none; }
        .badge-item {
            background: var(--bg-body); border: 1px solid var(--border);
            border-radius: var(--radius-sm); padding: 10px 15px;
            min-width: 160px; display: flex; align-items: center; gap: 12px;
        }
        .badge-icon { font-size: 24px; }
        .badge-txt h4 { margin: 0; font-size: 13px; color: var(--text-main); }
        .badge-txt span { font-size: 11px; color: var(--text-muted); }
        
        /* AUTH SCREEN */
        .auth-container { max-width: 400px; margin: auto; text-align: center; margin-top: 50px; position: relative; z-index: 10;}
        .alert { padding: 15px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 14px; font-weight: 600; text-align: left;}
        .alert-error { background: rgba(239, 68, 68, 0.1); color: var(--error); }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        
        .auth-form { display: flex; flex-direction: column; gap: 15px; }
        .switch-link { margin-top: 20px; font-size: 14px; color: var(--text-muted); }
        .switch-link a { color: var(--primary); text-decoration: none; font-weight: 700; }
        
        @media (max-width: 600px) {
            .habit-info { min-width: 140px; padding: 10px; }
            .btn-check { width: 32px; height: 32px; }
            .top-bar { flex-direction: column; align-items: flex-start; gap: 15px; }
            .controls { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body>

<?php if (!$currentUser): ?>
    <div class="auth-container">
        <div class="card">
            <h1 style="margin: 0 0 10px 0;">Daily Habits 🎯</h1>
            <p style="color:var(--text-muted); margin-bottom:30px; line-height:1.5;">
                Bangun kebiasaan baikmu<br> dengan satu langkah setiap hari.
            </p>
            
            <?php if ($errorMessage): ?><div class="alert alert-error"><?php echo $errorMessage; ?></div><?php endif; ?>
            <?php if ($successMessage): ?><div class="alert alert-success"><?php echo $successMessage; ?></div><?php endif; ?>
            
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="<?php echo $showRegister ? 'register' : 'login'; ?>">
                
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <?php if ($showRegister): ?>
                    <input type="password" name="confirm_password" placeholder="Konfirmasi Password" required>
                <?php endif; ?>
                
                <button type="submit" class="btn-primary">
                    <?php echo $showRegister ? 'DAFTAR AKUN' : 'MASUK'; ?>
                </button>
            </form>
            
            <div class="switch-link">
                <?php if ($showRegister): ?>
                    Sudah punya akun silahkan? <a href="?">Login</a>
                <?php else: ?>
                    Belum punya akun silakan? <a href="?register=1">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="app-container">
        <div class="top-bar">
            <div class="header-card">
                <div class="logo-area">
                    <h1>Halo, <?php echo htmlspecialchars($currentUser); ?>! <span class="emoji">👋</span></h1>
                    <p><?php echo date('d M', strtotime($mondayStr)) . ' - ' . date('d M', strtotime("$mondayStr +6 days")); ?></p>
                </div>
            </div>
            
            <div class="controls">
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="toggle_theme">
                    <button type="submit" class="theme-toggle" title="Ganti Tema">
                        <?php echo $_SESSION['theme'] === 'dark' ? '☀️' : '🌙'; ?>
                    </button>
                </form>

                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="logout-btn">LOGOUT</button>
                </form>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-val"><?php echo $totalCompletions; ?></div>
                <div class="stat-label">Total Check-ins</div>
            </div>
            <div class="stat-card">
                <div class="stat-val"><?php echo count($habits); ?></div>
                <div class="stat-label">Active Habits</div>
            </div>
            <div class="stat-card">
                <div class="stat-val"><?php echo $maxStreak ?? 0; ?></div>
                <div class="stat-label">Best Streak</div>
            </div>
        </div>
        
        <div class="card">
            <form method="POST" class="input-group" style="margin-bottom: 25px;">
                <input type="hidden" name="action" value="add">
                <input type="text" name="habit_name" placeholder="Mau rutin ngapain?" autocomplete="off" required>
                <button type="submit" class="btn-primary">TAMBAH</button>
            </form>
            
            <div class="table-wrapper">
                <table class="habit-table">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding-left:15px; color:var(--text-muted); font-size:12px;">HABITS & PROGRESS</th>
                            <?php foreach ($periodDates as $date): ?>
                                <?php $isToday = ($date === $today); ?>
                                <th class="th-day <?php echo $isToday ? 'current-day' : ''; ?>">
                                    <div class="date-capsule">
                                        <span class="day-name"><?php echo substr(date('l', strtotime($date)), 0, 3); ?></span>
                                        <span class="day-num"><?php echo date('d', strtotime($date)); ?></span>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($habits)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-muted);">Belum ada habit. Tambahkan satu di atas! 🚀</td></tr>
                        <?php else: ?>
                            <?php foreach ($habits as $habit): ?>
                                <?php
                                    // Hitung Progress Mingguan
                                    $weeklyChecks = 0;
                                    foreach ($periodDates as $d) {
                                        if ($habit->isCompletedOn($d)) $weeklyChecks++;
                                    }
                                    $percent = round(($weeklyChecks / 7) * 100);
                                ?>
                                <tr class="habit-row">
                                    <td>
                                        <div class="habit-info">
                                            <div class="habit-title">
                                                <?php echo htmlspecialchars($habit->getName()); ?>
                                                <form method="POST" onsubmit="return confirm('Hapus?');" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $habit->getId(); ?>">
                                                    <button type="submit" class="btn-delete" title="Hapus">&times;</button>
                                                </form>
                                            </div>
                                            <div class="habit-meta">
                                                <span>🔥 <?php echo $habit->getCurrentStreak(); ?> streak</span>
                                                <span style="margin-left:auto;"><?php echo $percent; ?>%</span>
                                            </div>
                                            <div class="progress-track">
                                                <div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <?php foreach ($periodDates as $date): ?>
                                        <?php 
                                            $isChecked = $habit->isCompletedOn($date);
                                            $isFuture = $date > $today;
                                        ?>
                                        <td class="check-wrapper <?php echo $isChecked ? 'is-checked' : ''; ?>">
                                            <?php if ($isFuture): ?>
                                                <div class="future-box"></div>
                                            <?php else: ?>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="toggle_date">
                                                    <input type="hidden" name="id" value="<?php echo $habit->getId(); ?>">
                                                    <input type="hidden" name="date" value="<?php echo $date; ?>">
                                                    <button type="submit" class="btn-check">
                                                        <?php echo $isChecked ? '✓' : ''; ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <h3 style="margin:0 0 15px 0; font-size:16px;">Pencapaian</h3>
            <div class="badges-scroll">
                <?php if (empty($globalBadges)): ?>
                    <div style="color:var(--text-muted); font-size:13px; font-style:italic;">Selesaikan habit untuk membuka badge!</div>
                <?php else: ?>
                    <?php foreach($globalBadges as $badge): ?>
                        <div class="badge-item">
                            <div class="badge-icon"><?php echo $badge['icon']; ?></div>
                            <div class="badge-txt">
                                <h4><?php echo $badge['title']; ?></h4>
                                <span><?php echo $badge['desc']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
</body>
</html>