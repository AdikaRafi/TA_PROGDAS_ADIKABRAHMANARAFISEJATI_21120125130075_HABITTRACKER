<?php
/**
 * Class AchievementManager
 * untuk memberikan lencana (badges) berdasarkan aktivitas pengguna.
 */
class AchievementManager {
    /**
     * Mendapatkan daftar badge yang diraih user.
     * 
     * @param int $streak Jumlah streak saat ini.
     * @param int $total Total penyelesaian habit.
     * @return array Daftar badge yang didapatkan.
     */
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
