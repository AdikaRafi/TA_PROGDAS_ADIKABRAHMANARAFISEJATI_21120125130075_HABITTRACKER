<?php
/**
 * Class Habit (Base Class)
 * Struktur dasar sebuah kebiasaan.
 */
class Habit {
    protected string $name;
    protected string $id;
    
    /**
     * Constructor Habit.
     * 
     * @param string $name Nama kebiasaan.
     * @param string|null $id ID unik kebiasaan (opsional).
     */
    public function __construct(string $name, ?string $id = null) {
        $this->name = $name;
        // Generate ID unik jika tidak disediakan
        $this->id = $id ?? uniqid();
    }
    /**
     * Mendapatkan nama kebiasaan.
     * @return string
     */
    public function getName(): string { return $this->name; }

    /**
     * Mendapatkan ID kebiasaan.
     * @return string
     */
    public function getId(): string { return $this->id; }
}

/**
 * Class TrackerHabit
 * Memperluas Habit dengan pelacakan tanggal dan streak.
 */
class TrackerHabit extends Habit {
    private array $completedDates; // Array tanggal yang sudah diceklis (Format: Y-m-d)

    /**
     * Constructor TrackerHabit.
     * 
     * @param string $name Nama kebiasaan.
     * @param string|null $id ID unik kebiasaan.
     * @param array $completedDates Daftar tanggal yang sudah diceklis.
     */
    public function __construct(string $name, ?string $id = null, array $completedDates = []) {
        parent::__construct($name, $id);
        $this->completedDates = $completedDates;
    }

    /**
     * Cek apakah habit sudah dilakukan pada tanggal tertentu.
     * 
     * @param string $date Tanggal dalam format Y-m-d.
     * @return bool True jika sudah dilakukan.
     */
    public function isCompletedOn(string $date): bool {
        return in_array($date, $this->completedDates);
    }

    /**
     * Fungsi untuk menceklis atau membatalkan ceklis (Toggle).
     * 
     * @param string $date Tanggal yang akan di-toggle (Y-m-d).
     */
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

    /**
     * Menghitung total berapa kali habit dilakukan.
     * @return int Total checklist.
     */
    public function getTotalCompletions(): int { return count($this->completedDates); }
    
    /**
     * Mengubah objek menjadi array untuk disimpan ke JSON.
     * @return array Data habit dalam bentuk array.
     */
    public function toArray(): array {
        return ['id' => $this->id, 'name' => $this->name, 'completed_dates' => $this->completedDates];
    }
}
