<?php
/**
 * Class JsonLibrary
 * Mengelola pembacaan dan penulisan data Habit ke file JSON user.
 */
class JsonLibrary {
    private string $filePath;
    
    /**
     * Constructor JsonLibrary.
     * 
     * @param string $filePath Path ke file JSON yang akan dikelola.
     */
    public function __construct(string $filePath) {
        $this->filePath = $filePath;
        if (!file_exists($filePath)) file_put_contents($filePath, json_encode([]));
    }

    /**
     * Mengambil data dari JSON dan mengubahnya menjadi Objek TrackerHabit.
     * 
     * @return array Daftar objek TrackerHabit.
     */
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

    /**
     * Menyimpan array Objek TrackerHabit kembali ke JSON.
     * 
     * @param array $habits Daftar objek habit yang akan disimpan.
     */
    public function saveData(array $habits): void {
        $data = array_map(fn($h) => $h->toArray(), $habits);
        file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT));
    }
}
