# Implementation Tasks: Chapter Revision Snapshots & Rollback

## Document Information
- **Feature Name:** Chapter Revision Snapshots & Rollback
- **Issue ID:** #04 (`04-chapter-revision-snapshots-rollback.md`)
- **Status:** Completed (All Tests Passed)
- **Specification Path:** `.kiro/specs/chapter-revisions/`

---

## Task Hierarchy & Phases

### Fase 1: Foundation (Database & Eloquent Model)
- [x] **Task 1.1: Migration Skema Basis Data**
  - Buat file migrasi `create_chapter_revisions_table.php` dengan kolom `id`, `chapter_id`, `title`, `type` (`auto`/`manual`), `content` (longText/json), `word_count`, dan `timestamps`.
  - Tambahkan indeks gabungan `['chapter_id', 'type', 'created_at']` dan FK cascading delete.
  - _Requirements: REQ-4.2, NFR-2_
- [x] **Task 1.2: Eloquent Model `ChapterRevision` & Relasi**
  - Buat model `App\Models\ChapterRevision` dengan properti `$fillable`, cast `content => array`, dan relasi `belongsTo(Chapter::class)`.
  - Perbarui model `App\Models\Chapter` dengan relasi `hasMany(ChapterRevision::class, 'chapter_id')`.
  - _Requirements: REQ-1.1, REQ-4.2_

---

### Fase 2: Business Logic & Snapshot Engine
- [x] **Task 2.1: Helper Penghitung Kata & Ekstraksi Teks**
  - Buat metode statis/helper pada `ChapterRevision` untuk mengekstrak teks polos dari Tiptap JSON document dan menghitung total kata (`word_count`).
  - _Requirements: REQ-2.5_
- [x] **Task 2.2: Mekanisme Auto-Snapshot dengan Interval 5 Menit**
  - Implementasikan logika pengecekan waktu snapshot terakhir. Jika kurang dari 300 detik dan konten tidak berubah, tahan pembuatan snapshot.
  - _Requirements: REQ-1.1, REQ-1.2_
- [x] **Task 2.3: Mekanisme Rotasi Batas Kuota 50 Snapshot (FIFO Pruning)**
  - Implementasikan pembersihan otomatis jika jumlah snapshot bertipe `auto` melebihi 50 entri per bab. Pastikan snapshot bertipe `manual` tidak tersentuh.
  - _Requirements: REQ-1.3, REQ-1.4, NFR-2_

---

### Fase 3: Controller & API Routing
- [x] **Task 3.1: Controller `ChapterRevisionController`**
  - Implementasikan method `index()` (daftar revisi tanpa content besar untuk efisiensi performa).
  - Implementasikan method `show()` (mengambil detail snapshot tertentu untuk pratinjau).
  - Implementasikan method `storeAuto()` (pembuatan auto-snapshot berkala).
  - Implementasikan method `storeCheckpoint()` (pembuatan manual checkpoint bernama).
  - Implementasikan method `restore()` (membuat safety snapshot dari draf mutakhir, lalu menimpa konten chapter dengan konten revisi target).
  - _Requirements: REQ-1.1, REQ-2.1, REQ-2.2, REQ-2.3, REQ-3.1, REQ-3.3, REQ-4.1, NFR-1_
- [x] **Task 3.2: Pendaftaran Route di `routes/web.php`**
  - Daftarkan endpoint-endpoint revisi di dalam grup middleware `auth` dengan scoped binding.
  - _Requirements: REQ-4.1_

---

### Fase 4: Frontend UI & Editor Integration
- [x] **Task 4.1: Komponen `RevisionDrawer.vue`**
  - Buat panel geser (*slide-over drawer*) untuk menampilkan daftar revisi dengan badge status, relative timestamp, word count, dan tombol preview.
  - Implementasikan modal pratinjau konten revisi (*read-only preview*) dan tombol konfirmasi "Pulihkan Versi Ini".
  - _Requirements: REQ-2.4, REQ-2.5, REQ-2.6, REQ-3.1, REQ-3.2_
- [x] **Task 4.2: Integrasi ke `ChapterEditor.vue`**
  - Tambahkan tombol "Riwayat Versi" dan "Simpan Checkpoint" di toolbar editor.
  - Pasang timer interval pengetikan aktif yang mengirim auto-snapshot setiap 5 menit jika konten kotor (*dirty*).
  - Pasang listener event `restored` untuk memperbarui konten Tiptap di browser secara sinkron tanpa reload halaman penuh.
  - _Requirements: REQ-1.1, REQ-2.1, REQ-3.4, NFR-3_

---

### Fase 5: Automated Testing & Verification
- [x] **Task 5.1: Unit & Feature Test Suite (Pest)**
  - Buat `tests/Feature/ChapterRevisionTest.php` mencakup:
    1. Otentikasi dan otorisasi isolasi project.
    2. Pembuatan auto-snapshot dan interval 5 menit.
    3. Batasan rotasi 50 auto-snapshot (FIFO pruning).
    4. Kekebalan manual checkpoint dari rotasi FIFO.
    5. Integritas rollback dan safety snapshot otomatis.
    6. Cascading deletion saat chapter atau project dihapus.
  - _Requirements: REQ-1.1 s/d REQ-4.2_
- [x] **Task 5.2: Regresi Test Keseluruhan**
  - Jalankan `php artisan test` untuk memastikan seluruh 55 test awal + test baru lolos 100%.
  - Jalankan `npm run build` untuk memvalidasi kompilasi aset Vue 3 tanpa error sintaksis.
  - _Requirements: NFR-1, NFR-3_

---

## Quality Gates
- [x] **Traceability Gate**: Setiap task merujuk ke ID requirement EARS yang sah.
- [x] **Completeness Gate**: Mencakup database, backend logic, API, frontend drawer, dan automated tests.
- [x] **Safety Gate**: Menyertakan safety snapshot sebelum rollback untuk mencegah kehilangan data mahasiswa secara permanen.
