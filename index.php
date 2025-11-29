<?php
// ==========================================
// BAGIAN 1: SETUP & KONFIGURASI DASAR
// ==========================================

// Mengatur zona waktu agar sesuai dengan lokasi pengguna (WIB)
date_default_timezone_set('Asia/Jakarta');

// Memulai sesi untuk menyimpan data login dan preferensi sementara
session_start();

// Mengatur tema default (Light Mode) jika belum ada di sesi
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}

// Mendefinisikan lokasi file JSON untuk menyimpan data akun pengguna
define('USERS_FILE', 'users.json');

// ==========================================
// BAGIAN 2: CLASS AUTHENTICATION (KEAMANAN)
// ==========================================

/**
 * Class UserAuth
 * untuk menangani registrasi, login, dan penyimpanan data user.
 */
class UserAuth {
    private string $usersFile;
    
    public function __construct(string $usersFile) {
        $this->usersFile = $usersFile;
        // Jika file database user belum ada, buat file baru dengan array kosong
        if (!file_exists($usersFile)) {
            file_put_contents($usersFile, json_encode([]));
        }
    }
    
    // Mengambil semua data user dari file JSON
    private function getUsers(): array {
        return json_decode(file_get_contents($this->usersFile), true) ?? [];
    }
    
    // Menyimpan array user kembali ke file JSON
    private function saveUsers(array $users): void {
        file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT));
    }
    
    /**
     * Mendaftarkan pengguna baru dengan validasi input.
     */
    public function register(string $username, string $password): array {
        // Validasi input dasar
        if (strlen($username) < 3) return ['success' => false, 'message' => 'Username minimal 3 karakter'];
        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) return ['success' => false, 'message' => 'Username hanya huruf & angka'];
        if (strlen($password) < 8) return ['success' => false, 'message' => 'Password minimal 8 karakter'];
        
        $users = $this->getUsers();
        // Cek apakah username sudah dipakai
        foreach ($users as $user) {
            if ($user['username'] === $username) return ['success' => false, 'message' => 'Username sudah terdaftar'];
        }
        
        // Simpan user baru dengan password yang di-hash (dienkripsi)
        $users[] = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->saveUsers($users);
        return ['success' => true, 'message' => 'Registrasi berhasil!'];
    }
    
    /**
     * Memverifikasi login pengguna.
     */
    public function login(string $username, string $password): array {
        $users = $this->getUsers();
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                // Verifikasi hash password
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

// ==========================================
// BAGIAN 3: HANDLER REQUEST (POST)
// ==========================================

// Inisialisasi objek Auth
$auth = new UserAuth(USERS_FILE);
$errorMessage = '';
$successMessage = '';
$habitErrorMessage = '';

// Mengecek apakah ada request POST (Pengiriman Formulir)
if (isset($_POST['action'])) {
    
    // --- FITUR GANTI TEMA ---
    if ($_POST['action'] === 'toggle_theme') {
        // Toggle antara 'light' dan 'dark'
        $_SESSION['theme'] = ($_SESSION['theme'] === 'light') ? 'dark' : 'light';
        // Refresh halaman agar perubahan langsung terasa dan mencegah resubmit form
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // --- PROSES REGISTRASI ---
    if ($_POST['action'] === 'register') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if ($password !== $confirmPassword) {
            $errorMessage = 'Password tidak sama';
        } else {
            $result = $auth->register($username, $password);
            if ($result['success']) {
                // Auto login setelah register sukses
                $_SESSION['user'] = $username;
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $errorMessage = $result['message'];
            }
        }
    } 
    // --- PROSES LOGIN ---
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
    // --- PROSES LOGOUT ---
    elseif ($_POST['action'] === 'logout') {
        // Simpan preferensi tema sebelum menghancurkan sesi
        $currentTheme = $_SESSION['theme']; 
        session_destroy();
        
        // Mulai sesi baru hanya untuk menyimpan tema kembali
        session_start();
        $_SESSION['theme'] = $currentTheme;
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Menentukan file database habit user yang sedang login
$currentUser = $_SESSION['user'] ?? null;
// Format nama file: data_username.json
$databaseFile = $currentUser ? 'data_' . $currentUser . '.json' : null;

// ==========================================
// BAGIAN 4: CLASSES HABIT & LOGIC (MODEL)
// ==========================================

/**
 * Class Habit (Base Class)
 * Struktur dasar sebuah kebiasaan.
 */
class Habit {
    protected string $name;
    protected string $id;
    
    public function __construct(string $name, ?string $id = null) {
        $this->name = $name;
        // Generate ID unik jika tidak disediakan
        $this->id = $id ?? uniqid();
    }
    public function getName(): string { return $this->name; }
    public function getId(): string { return $this->id; }
}

/**
 * Class TrackerHabit
 * Memperluas Habit dengan fungsionalitas pelacakan tanggal dan streak.
 */
class TrackerHabit extends Habit {
    private array $completedDates; // Array tanggal yang sudah diceklis (Format: Y-m-d)

    public function __construct(string $name, ?string $id = null, array $completedDates = []) {
        parent::__construct($name, $id);
        $this->completedDates = $completedDates;
    }

    // Cek apakah habit sudah dilakukan pada tanggal tertentu
    public function isCompletedOn(string $date): bool {
        return in_array($date, $this->completedDates);
    }

    // Fungsi untuk menceklis atau membatalkan ceklis (Toggle)
    public function toggleDate(string $date): void {
        if ($this->isCompletedOn($date)) {
            // Jika sudah ada, hapus dari array (Uncheck)
            $this->completedDates = array_diff($this->completedDates, [$date]);
        } else {
            // Jika belum ada, tambahkan ke array (Check)
            $this->completedDates[] = $date;
        }
        // Re-index array agar rapi
        $this->completedDates = array_values($this->completedDates);
    }

    /**
     * Menghitung Streak (Keteraturan berturut-turut).
     * Logika: Menghitung mundur dari hari ini atau kemarin.
     */
    public function getCurrentStreak(): int {
        $streak = 0;
        $checkDate = new DateTime(); // Mulai dari hari ini

        while (true) {
            $dateStr = $checkDate->format('Y-m-d');
            
            if ($this->isCompletedOn($dateStr)) {
                $streak++;
                $checkDate->modify('-1 day'); // Mundur satu hari ke belakang
            } else {
                // Jika hari ini belum dicentang, jangan putus streak dulu kalau streak > 0,
                // Tapi logika sederhana ini memutus jika hari yang dicek kosong.
                break; 
            }
        }

        return $streak;
    }

    public function getTotalCompletions(): int { return count($this->completedDates); }
    
    // Mengubah objek menjadi array untuk disimpan ke JSON
    public function toArray(): array {
        return ['id' => $this->id, 'name' => $this->name, 'completed_dates' => $this->completedDates];
    }
}

/**
 * Class AchievementManager
 *untuk memberikan lencana (badges) berdasarkan performa.
 */
class AchievementManager {
    public static function getBadges(int $streak, int $total): array {
        $badges = [];
        // Logika pemberian badge berdasarkan total check dan streak
        if ($total >= 1) $badges[] = ['icon' => '🌱', 'title' => 'First Steps', 'desc' => 'Start Langkah Pertamamu'];
        if ($streak >= 3) $badges[] = ['icon' => '🔥', 'title' => 'Fire Streak', 'desc' => 'Kamu sudah Konsisten 3 hari berturut-turut!'];
        if ($streak >= 7) $badges[] = ['icon' => '👑', 'title' => 'Habit Hero', 'desc' => 'Kamu Sudah Konsisten Selama Seminggu penuh!'];
        if ($streak >= 14) $badges[] = ['icon' => '⚡', 'title' => 'Unstoppable Person', 'desc' => 'SELAMAT KAMU SUDAH KONSISTEN SELAMA 2 MINGGU!'];
        if ($total >= 30) $badges[] = ['icon' => '💎', 'title' => 'Master', 'desc' => 'WOWW KONSISTEN 30 HARI'];
        if ($total >= 50) $badges[] = ['icon' => '🏆', 'title' => 'World Champion', 'desc' => 'ANDA ADALAH JUARA KARENA KONSISTEN SELAMA 50 HARII!'];
        return $badges;
    }
}

/**
 * Class JsonLibrary
 * Mengelola pembacaan dan penulisan data Habit ke file JSON pengguna.
 */
class JsonLibrary {
    private string $filePath;
    
    public function __construct(string $filePath) {
        $this->filePath = $filePath;
        if (!file_exists($filePath)) file_put_contents($filePath, json_encode([]));
    }

    // Mengambil data dari JSON dan mengubahnya menjadi Objek TrackerHabit
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

    // Menyimpan array Objek TrackerHabit kembali ke JSON
    public function saveData(array $habits): void {
        $data = array_map(fn($h) => $h->toArray(), $habits);
        file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT));
    }
}

// ==========================================
// BAGIAN 5: LOGIKA UTAMA APLIKASI
// ==========================================

$habits = [];
$totalStreak = 0;
$totalCompletions = 0;
$globalBadges = [];
$periodDates = [];

// Setup Tanggal: Mengambil tanggal hari ini dan tanggal Senin-Minggu ini
$today = date('Y-m-d');
$mondayStr = date('Y-m-d', strtotime('monday this week'));

// Loop untuk membuat array tanggal seminggu penuh (Senin s/d Minggu)
for ($i = 0; $i < 7; $i++) {
    $periodDates[] = date('Y-m-d', strtotime("$mondayStr +$i days"));
}

// Jika user sudah login, jalankan logika habit
if ($currentUser) {
    $library = new JsonLibrary($databaseFile);
    $habits = $library->loadData();

    // --- SORTING HABIT ---
    // Mengurutkan habit: Yang sudah diceklis hari ini ditaruh di ATAS (return -1)
    // Jika status sama, urutkan berdasarkan Abjad Nama.
    usort($habits, function($a, $b) use ($today) {
        $aToday = $a->isCompletedOn($today);
        $bToday = $b->isCompletedOn($today);

        // Prioritas sorting berdasarkan status checklist hari ini
        if ($aToday && !$bToday) return -1; // $a naik ke atas
        if (!$aToday && $bToday) return 1;  // $b naik ke atas

        // Secondary sorting: Alfabetis
        return strcmp(strtolower($a->getName()), strtolower($b->getName()));
    });

    // --- HANDLER AKSI HABIT (CRUD) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // 1. Tambah Habit Baru
        if ($action === 'add' && !empty(trim($_POST['habit_name']))) {
            $habitName = htmlspecialchars(trim($_POST['habit_name']), ENT_QUOTES, 'UTF-8');
            
            // Cek duplikasi nama habit agar tidak ada yang kembar
            $isDuplicate = false;
            foreach ($habits as $h) {
                if (strcasecmp($h->getName(), $habitName) === 0) {
                    $isDuplicate = true;
                    $habitErrorMessage = 'Kebiasaan sudah terdaftar';
                    break;
                }
            }

            if (!$isDuplicate) {
                $habits[] = new TrackerHabit(trim($_POST['habit_name']));
                $library->saveData($habits);
            }
        }
        
        // 2. Toggle Status (Check/Uncheck)
        if ($action === 'toggle_date') {
            // Validasi: Tidak boleh menceklis tanggal masa depan
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

        // 3. Hapus Habit
        if ($action === 'delete') {
            // Filter array untuk membuang habit dengan ID yang dipilih
            $habits = array_filter($habits, fn($h) => $h->getId() !== $_POST['id']);
            $library->saveData($habits);
        }
        
        // Redirect PRG (Post-Redirect-Get) pattern untuk mencegah resubmit form
        // Kecuali jika ada error atau aksi logout/toggle tema
        if ($action !== 'logout' && $action !== 'toggle_theme' && empty($habitErrorMessage)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    
    // --- KALKULASI STATISTIK ---
    foreach($habits as $h) {
        $totalStreak += $h->getCurrentStreak();
        $totalCompletions += $h->getTotalCompletions();
    }
    
    // Cari streak tertinggi dari semua habit untuk menentukan badge
    $maxStreak = empty($habits) ? 0 : max(array_map(fn($h)=>$h->getCurrentStreak(), $habits));
    $globalBadges = AchievementManager::getBadges($maxStreak, $totalCompletions);
}
// Menentukan apakah form register perlu ditampilkan (berdasarkan query string ?register)
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
        /* =========================================
           1. VARIABEL CSS (THEMING)
           ========================================= */
        :root 
        {
            /* Light Theme — Warna default (Mode Terang) */
            --bg-body: #F5F7FA;         /* Background halaman */
            --bg-card: #FFFFFF;         /* Background kartu/kotak */
            --text-main: #374B63;       /* Warna teks utama */
            --text-muted: #77859A;      /* Warna teks pudar/keterangan */
            
            --primary: #6C7AE0;         /* Warna utama (Ungu/Biru) */
            --primary-light: #EEF1FF;
            
            --accent: #F5B453;          /* Warna aksen (Oranye) */
            --border: #E1E6EF;          /* Warna garis tepi */
            
            --success: #4BBF8C;         /* Warna hijau sukses */
            --error: #EA6A6A;           /* Warna merah error */
            
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            --shadow-hover: 0 10px 25px rgba(108, 122, 224, 0.18);
            
            --radius: 24px;             /* Kelengkungan sudut besar */
            --radius-sm: 12px;          /* Kelengkungan sudut kecil */
            --progress-bg: #3f4e86;     /* Background track progress bar */
            --pattern-color: #AEB7C8;   /* Warna pola grid background */
            --texture-shadow: rgba(0, 0, 0, 0.04);
        }
        
        /* Override variabel untuk Mode Gelap */
        [data-theme="dark"] 
        {
            --bg-body: #121923;         /* Background gelap */
            --bg-card: #1C2532;         /* Kartu sedikit lebih terang dari bg */
            --text-main: #E7ECF3;       /* Teks menjadi putih/terang */
            --text-muted: #9AA8B9;      
            --primary: #7D8CFF;         
            --primary-light: #2D3357;
            
            --accent: #F2C063;          
            --border: #2A3342;
            
            --success: #4FD4A7;
            --error: #F28A8A;
            --shadow: 0 4px 20px rgba(0,0,0,0.25);
            --shadow-hover: 0 10px 25px rgba(0,0,0,0.35);
            --progress-bg: #2E3646;
            
            --pattern-color: #2E3646;   
            --texture-shadow: rgba(255,255,255,0.06);
        }
        
        /* =========================================
           2. RESET & LAYOUT DASAR
           ========================================= */
        * {
            box-sizing: border-box;
            /* Transisi halus saat ganti tema */
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            /* Membuat pola grid kotak-kotak halus di background */
            background-image: 
                linear-gradient(var(--pattern-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--pattern-color) 1px, transparent 1px),
                radial-gradient(circle at 40% 40%, var(--texture-shadow), transparent 60%);
            background-size: 
                22px 22px, 
                22px 22px,
                100% 100%;
            box-shadow: inset 0 0 80px var(--texture-shadow);
            color: var(--text-main);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center; /* Posisi konten di tengah */
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
        
        /* =========================================
           3. KOMPONEN UI (Header, Card, Input)
           ========================================= */
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
        /* Memperbaiki tampilan Emoji agar tidak terkena efek gradient text */
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
        
        /* Tombol Ganti Tema (Bulan/Matahari) */
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
        
        /* =========================================
           4. TABEL HABIT
           ========================================= */
        .table-wrapper { overflow-x: auto; margin: 0 -25px; padding: 0 25px; padding-bottom: 10px;}
        .habit-table { width: 100%; border-collapse: separate; border-spacing: 0 15px; }
        
        /* Header Hari (Sen, Sel, Rab...) */
        .th-day { text-align: center; min-width: 50px; padding-bottom: 10px; }
        .date-capsule { display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .day-name { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); }
        .day-num { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 13px; font-weight: 700; color: var(--text-main); }
        /* Style khusus hari ini */
        .current-day .day-num { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        
        .habit-row td { vertical-align: middle; }
        
        /* Info Habit (Nama, Streak, Progress Bar) - Sticky Left */
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
        
        /* Progress Bar */
        .progress-track { height: 4px; background: var(--progress-bg); border-radius: 2px; width: 100%; margin-top: 10px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--accent)); border-radius: 2px; transition: width 0.5s ease-out; }
        
        /* Checkbox Kustom */
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
        
        /* State ketika sudah dicentang */
        .is-checked .btn-check {
            background: var(--success);
            border-color: var(--success);
            color: white;
            transform: scale(1.05);
        }
        /* Kotak kosong untuk masa depan */
        .future-box { width: 38px; height: 38px; border-radius: 12px; background: var(--border); opacity: 0.3; margin: auto; }
        
        .btn-delete { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px; padding: 0; opacity: 0.6; transition: 0.2s;}
        .btn-delete:hover { color: var(--error); opacity: 1; }
        
        /* =========================================
           5. STATISTIK & BADGES
           ========================================= */
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
        
        /* =========================================
           6. HALAMAN AUTH (LOGIN/REGISTER)
           ========================================= */
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
                Bangun kebiasaan baikmu<br> dengan satu langkah baru setiap hari.
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
            <?php if ($habitErrorMessage): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    <?php echo $habitErrorMessage; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="input-group" style="margin-bottom: 25px;">
                <input type="hidden" name="action" value="add">
                <input type="text" name="habit_name" placeholder="Tambah Kebiasaan Baik" autocomplete="off" required>
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
                                    //Hitung persentase progress minggu ini
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
                                            $isFuture = $date > $today; // Cek tanggal masa depan
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
