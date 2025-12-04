# 📘 HabitTracker
*Proyek ini dibuat dalam rangka untuk mengerjakan TUGAS AKHIR PRAKTIKUM PEMROGRAMAN DASAR.*

*Aplikasi berbasis Web Sederhana untuk Membangun & Menjaga Kebiasaan Baik Kita*

HabitTracker adalah aplikasi berbasis web yang dirancang untuk membantu pengguna melacak kebiasaan harian pengguna dengan mudah. Dibuat menggunakan **PHP**, **HTML**, dan **CSS**, aplikasi ini cocok sebagai latihan dasar pemrograman web sekaligus alat sederhana untuk memantau pengembangan diri.


---

## ✨ Fitur Utama

* ✔️ **Menambah, Mengedit, dan Menghapus kebiasaan** baru dengan mudah
* 📅 **Menandai kebiasaan yang sudah dilakukan** setiap hari.
* 📊 **Melihat status kebiasaan** (done / belum) secara visual.
* 🗂️ Penyimpanan sederhana menggunakan JSON.
* 🏆 Ada sistem achievement(badge) untuk setiap progress yang dilakukan secara konsisten.
* 🎨 Tampilan simpel, ringan serta mudah dimengerti.
* 🪄 Ada 2 Tema, Light Mode dan Dark Mode.
---

## 🚀 Tech Stack
* Backend: PHP.
* Frontend: HTML, CSS.
* Data Storage: JSON (sebagai penyimpanan sederhana untuk data habit dan user).
* Environment: Apache Server melalui XAMPP.
* Version Control: Git + GitHub.

---

## 📁 Struktur Folder

```
├──index.php          → Halaman utama aplikasi   
├──style.css          → Tampilan UI
├──localdata          → Tempat data pengguna disimpan 
├──func/
    ├──UserAuth.php         → Class untuk autentikasi user (register, login, session)
    ├── Habit.php           → Class untuk manajemen habit (add, check, streak, dll)
    ├── Achievement.php     → Class untuk fitur pencapaian (achievement/streak reward)
    └── JsonLib.php         → Library JSON untuk load & save data (user, habit, log)
└──README.md          → Dokumentasi proyek  
```

---

## 🚀 Instalasi & Cara Menjalankan

1. **Clone repository:**

   ```bash
   git clone https://github.com/AdikaRafi/TA_PROGDAS_ADIKABRAHMANARAFISEJATI_21120125130075_HABITTRACKER.git
   ```

2. **Pindahkan folder project** ke:

   * `htdocs` (jika menggunakan XAMPP)
   * `www` (jika menggunakan Laragon)
   * atau folder public server lokal lainnya

3. **Jalankan server Apache**

4. Buka di browser:

   ```
   http://localhost/TA_PROGDAS_ADIKABRAHMANARAFISEJATI_21120125130075_HABITTRACKER-master/index.php
   ```

Aplikasi siap digunakan!

---

## 📸 Demo Tampilan
<table> <tr> <td align="center"> <strong>Halaman Login</strong><br> <img width="330" height="420" src="https://github.com/user-attachments/assets/6257b048-e4bc-4fb7-aa43-5cc737715cfc" /> </td> <td align="center"> <strong>Halaman Register</strong><br> <img width="330" height="420" src="https://github.com/user-attachments/assets/4bcf4cee-29b5-4656-adb2-a6fe0548f352" /> </td> </tr> <tr> <td align="center"> <strong>Dashboard</strong><br> <img width="350" height="260" src="https://github.com/user-attachments/assets/03267f9c-6e33-440e-ada1-a45a256cec17" /> </td> <td align="center"> <strong>Habit Setelah Ditandai</strong><br> <img width="350" height="160" src="https://github.com/user-attachments/assets/35eaaf68-be9b-40cc-83a4-2f104359cd5d" /> </td> </tr> <tr> <td align="center"> <strong>Edit Nama Habit</strong><br> <img width="330" height="260" src="https://github.com/user-attachments/assets/df036112-5b38-4bad-a16d-2a93d4ebe406" /> </td> <td align="center"> <strong>Dark Mode</strong><br> <img width="350" height="180" src="https://github.com/user-attachments/assets/b0572742-b64c-458d-af67-4e0bc6113811" /> </td> </tr> </table>
---

## 🎯 Cara Menggunakan

1. Tambahkan kebiasaan baru pada kotak yang tersedia di dashboard.
2. Setiap hari, klik kotak check untuk menandai kebiasaan sudah dilakukan.
3. Habit akan tercatat dan bisa dilihat kembali.
4. Ulangi setiap hari untuk menjaga konsistensi dan meraih pencapaian(badge).

---

## 🔭 Roadmap Pengembangan Kedepannya

Fitur yang dapat ditambahkan di versi berikutnya:

* ⏱️ Statistik mingguan/bulanan
* 📊 Grafik progres
* 💾 Integrasi database (MySQL)

---

## 🤝 Kontribusi

Kontribusi Terbuka Untuk Umum!
Silakan fork repository ini, buat branch baru, lalu kirim **pull request**.

---

## 👤 Author

**Adika Brahmana Rafi Sejati**
GitHub: [https://github.com/AdikaRafi](https://github.com/AdikaRafi)

---

## 📄 Lisensi

Proyek ini menggunakan lisensi **MIT**.
Kamu bebas menggunakan, mengembangkan, dan mendistribusikannya.

