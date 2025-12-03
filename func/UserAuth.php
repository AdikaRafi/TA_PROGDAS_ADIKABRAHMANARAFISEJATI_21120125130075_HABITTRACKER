<?php
/**
 * Class UserAuth
 * untuk menangani registrasi, login, dan penyimpanan data user.
 */
class UserAuth {
    private string $usersFile;
    
    /**
     * Constructor UserAuth.
     * 
     * @param string $usersFile Path ke file JSON database user.
     */
    public function __construct(string $usersFile) {
        $this->usersFile = $usersFile;
        // Jika file database user belum ada, buat file baru dengan array kosong
        if (!file_exists($usersFile)) {
            file_put_contents($usersFile, json_encode([]));
        }
    }
    
    /**
     * Mengambil semua data user dari file JSON.
     * 
     * @return array Daftar user yang terdaftar.
     */
    private function getUsers(): array {
        return json_decode(file_get_contents($this->usersFile), true) ?? [];
    }
    
    /**
     * Menyimpan array user kembali ke file JSON.
     * 
     * @param array $users Array data user yang akan disimpan.
     */
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
