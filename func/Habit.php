<?php
/**
 * Class Habit (Base Class)
 * Struktur dasar sebuah untuk mengatur kebiasaan.
 */
class Habit {
    protected string $name;
    protected string $id;
    protected string $createdAt;
    
    /**
     * Constructor Habit.
     * 
     * @param string $name Nama kebiasaan.
     * @param string|null $id ID unik kebiasaan (opsional).
     * @param string|null $createdAt Tanggal pembuatan (Y-m-d).
     */
    public function __construct(string $name, ?string $id = null, ?string $createdAt = null) {
        $this->name = $name;
        $this->id = $id ?? uniqid();
        $this->createdAt = $createdAt ?? date('Y-m-d');
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getId(): string { return $this->id; }
    public function getCreatedAt(): string { return $this->createdAt; }
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
     * @param string|null $createdAt Tanggal pembuatan.
     */
    public function __construct(string $name, ?string $id = null, array $completedDates = [], ?string $createdAt = null) {
        parent::__construct($name, $id, $createdAt);
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
        
        // Perbaikan: Jika hari ini belum diceklis, cek mulai dari kemarin
        // agar streak tidak terlihat reset jadi 0.
        if (!$this->isCompletedOn($checkDate->format('Y-m-d'))) {
            $checkDate->modify('-1 day');
        }

        while (true) {
            $dateStr = $checkDate->format('Y-m-d');
            
            if ($this->isCompletedOn($dateStr)) {
                $streak++;
                $checkDate->modify('-1 day'); // Mundur satu hari ke belakang
            } else {
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
        return [
            'id' => $this->id, 
            'name' => $this->name, 
            'completed_dates' => $this->completedDates,
            'created_at' => $this->createdAt
        ];
    }
}
