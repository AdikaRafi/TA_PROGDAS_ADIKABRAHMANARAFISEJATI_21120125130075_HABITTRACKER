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
define('USERS_FILE', 'localsave/users.json');

// Memuat class-class yang dibutuhkan dari folder classes/
// Pastikan urutan require sesuai dengan dependensi antar class

// ==========================================
// BAGIAN 2: CLASS AUTHENTICATION & MODELS
// ==========================================
require_once 'func/UserAuth.php';
require_once 'func/Habit.php';
require_once 'func/Achievement.php';
require_once 'func/JsonLib.php';

// ==========================================
// BAGIAN 3: HANDLER REQUEST (POST)
// ==========================================

// Inisialisasi objek Auth untuk menangani login/register
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
        // Simpan preferensi tema sebelum mengeluarkan sesi
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
// Jika user belum login, $currentUser bernilai null
$currentUser = $_SESSION['user'] ?? null;
// Format nama file: data_username.json (unik per user)
$databaseFile = $currentUser ? 'localsave/data_' . $currentUser . '.json' : null;

// ==========================================
// BAGIAN 4: LOGIKA UTAMA APLIKASI
// ==========================================

$habits = [];
$totalStreak = 0;
$totalCompletions = 0;
$globalBadges = [];
$periodDates = [];

// Setup Tanggal: Mengambil tanggal hari ini dan tanggal Senin-Minggu ini
$today = date('Y-m-d');
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$mondayStr = date('Y-m-d', strtotime("monday this week $weekOffset weeks"));

// Loop untuk membuat array tanggal seminggu penuh (Senin s/d Minggu)
for ($i = 0; $i < 7; $i++) {
    $periodDates[] = date('Y-m-d', strtotime("$mondayStr +$i days"));
}

// Jika user sudah login, jalankan logika habit
if ($currentUser) {
    // Memuat data habit dari file JSON milik user
    $library = new JsonLibrary($databaseFile);
    $habits = $library->loadData();

    // --- SORTING HABIT ---
    // Mengurutkan habit: Yang sudah diceklis hari ini ditaruh di ATAS (return -1)
    // Jika status sama, diurutkan berdasarkan Abjad Nama.
    // Tujuannya agar user fokus pada habit yang BELUM dikerjakan hari ini.
    usort($habits, function($a, $b) use ($today) {
        $aToday = $a->isCompletedOn($today);
        $bToday = $b->isCompletedOn($today);

        // Prioritas sorting berdasarkan status checklist hari ini
        if ($aToday && !$bToday) return -1; // $a naik ke atas (sudah selesai)
        // Jadi yang SUDAH selesai ditaruh di ATAS.
        
        if ($aToday && !$bToday) return -1; 
        if (!$aToday && $bToday) return 1;

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
            $redirectUrl = $_SERVER['PHP_SELF'];
            if (isset($_GET['week'])) {
                $redirectUrl .= '?week=' . $_GET['week'];
            }
            header("Location: " . $redirectUrl);
            exit;
        }
    }
    
    // --- LOGIKA BATASAN NAVIGASI & FILTERING ---
    // 1. Cari tanggal pembuatan paling awal dari SEMUA habit (untuk batasan navigasi)
    $earliestHabitDate = $today;
    if (!empty($habits)) {
        $earliestHabitDate = min(array_map(fn($h) => $h->getCreatedAt(), $habits));
    }
    
    // 2. Cek apakah minggu sebelumnya sudah melewati batas tanggal pembuatan habit pertama
    $prevWeekEnd = date('Y-m-d', strtotime("$mondayStr -1 day"));
    $canGoBack = ($prevWeekEnd >= $earliestHabitDate);

    // 3. Filter Habit: Sembunyikan habit yang belum dibuat pada minggu yang sedang dilihat
    // Batas akhir minggu ini (Minggu)
    $currentWeekEnd = $periodDates[6]; 
    
    $habits = array_filter($habits, function($h) use ($currentWeekEnd) {
        // Tampilkan habit hanya jika tanggal pembuatannya <= tanggal akhir minggu ini
        return $h->getCreatedAt() <= $currentWeekEnd;
    });

    // --- KALKULASI STATISTIK (Berdasarkan habit yang tampil) ---
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
    
    <link rel="stylesheet" href="style.css">
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
                    <div class="week-nav">
                        <?php if ($canGoBack): ?>
                            <a href="?week=<?php echo $weekOffset - 1; ?>">&lt;</a>
                        <?php else: ?>
                            <span style="opacity:0.3; cursor:not-allowed; width:28px; height:28px; display:flex; align-items:center; justify-content:center; border:1px solid var(--border); border-radius:50%;">&lt;</span>
                        <?php endif; ?>
                        
                        <span><?php echo date('d M', strtotime($mondayStr)) . ' - ' . date('d M', strtotime("$mondayStr +6 days")); ?></span>
                        
                        <?php if ($weekOffset < 0): ?>
                            <a href="?week=<?php echo $weekOffset + 1; ?>">&gt;</a>
                        <?php endif; ?>
                    </div>
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
                                            $isBeforeCreation = $date < $habit->getCreatedAt(); // Cek sebelum dibuat
                                        ?>
                                        <td class="check-wrapper <?php echo $isChecked ? 'is-checked' : ''; ?>">
                                            <?php if ($isBeforeCreation): ?>
                                                <div class="future-box" style="opacity:0.2; border:none; background:var(--text-muted);" title="Belum dibuat"></div>
                                            <?php elseif ($isFuture): ?>
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
