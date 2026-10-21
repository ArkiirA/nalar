# Requirements Specification: Chapter Revision Snapshots & Rollback

## Document Information
- **Feature Name:** Chapter Revision Snapshots & Rollback
- **Issue ID:** #04 (`04-chapter-revision-snapshots-rollback.md`)
- **Status:** Draft -> Under Review
- **Owner:** Pram (Senior Developer & Spec Architect)
- **Domain:** Nalar Academic Workspace (`e:\Documents\nalar`)

---

## 1. Introduction
Fitur **Chapter Revision Snapshots & Rollback** menyediakan jaring pengaman (*safety net*) bagi mahasiswa saat menulis draf skripsi. Setiap perubahan teks pada Chapter Document (Bab atau Sub-bab) direkam secara berkala tanpa mengganggu pengetikan aktif, memungkinkan mahasiswa membuat *named checkpoint* manual sebelum bimbingan/revisi besar, melihat riwayat versi terdahulu secara visual, dan memulihkan (*rollback*) draf bab ke versi yang dipilih.

---

## 2. Functional Requirements (EARS-Compliant)

### 2.1 Automatic Background Snapshots
- **REQ-1.1 (Triggered Interval):**
  `WHILE` mahasiswa melakukan pengetikan aktif pada editor dan terdapat perubahan konten sejak snapshot terakhir, `THE` sistem `SHALL` membuat snapshot revisi otomatis (*auto-snapshot*) jika waktu sejak snapshot otomatis terakhir telah mencapai atau melampaui 5 menit (300 detik).
- **REQ-1.2 (No-op on Clean State):**
  `WHILE` editor tidak mengalami perubahan konten (konten identik dengan revisi tersimpan terakhir), `THE` sistem `SHALL` menahan dan tidak membuat snapshot revisi redundan.
- **REQ-1.3 (Cap Limit Rotation):**
  `WHEN` pembuatan snapshot baru menyebabkan jumlah revisi otomatis pada satu chapter melebihi 50 entri, `THE` sistem `SHALL` menghapus otomatis entri snapshot otomatis tertua (*FIFO rotation*) sehingga total snapshot otomatis tetap maksimal 50 per chapter.
- **REQ-1.4 (Protected Manual Checkpoints):**
  `WHILE` sistem melakukan rotasi pembersihan batas kuota 50 snapshot, `THE` sistem `SHALL` mengecualikan dan mempertahankan seluruh snapshot manual (*named checkpoint*) agar tidak terhapus oleh rotasi otomatis.

### 2.2 Manual Checkpoint Creation
- **REQ-2.1 (Named Snapshot Creation):**
  `WHEN` mahasiswa memasukkan nama versi pada modal checkpoint dan menekan tombol simpan, `THE` sistem `SHALL` menyimpan snapshot manual baru berisi kondisi mutakhir dokumen chapter dengan label nama yang diinputkan.
- **REQ-2.2 (Fallback Default Title):**
  `IF` mahasiswa mengosongkan nama versi saat membuat checkpoint manual, `THEN THE` sistem `SHALL` menetapkan nama default berbasis stempel waktu lokal (contoh: "Versi Manual - 21 Okt 2026 19:00").
- **REQ-2.3 (Save Sync Before Checkpoint):**
  `WHEN` pembuatan checkpoint manual dipicu, `THE` sistem `SHALL` memastikan perubahan terkini di editor telah ter-*flush* dan tersimpan ke database sebelum snapshot dibuat.

### 2.3 Revision History Drawer & Listing
- **REQ-2.4 (Open Drawer & Fetch List):**
  `WHEN` mahasiswa mengklik tombol riwayat revisi pada bilah atas editor, `THE` sistem `SHALL` membuka panel samping (*drawer*) riwayat revisi dan menampilkan daftar snapshot chapter terkait secara terurut dari yang terbaru (*chronological descending*).
- **REQ-2.5 (Item Metadata Display):**
  `FOR EACH` entri revisi di dalam daftar, `THE` sistem `SHALL` menampilkan judul versi (atau penanda auto-save), tipe revisi (`auto` / `manual`), jumlah kata (*word count*), dan estimasi waktu relatif (*relative timestamp*, misal: "5 menit yang lalu").
- **REQ-2.6 (Empty State Handling):**
  `IF` chapter belum memiliki riwayat revisi selain versi awal, `THEN THE` sistem `SHALL` menampilkan *empty state card* yang menginformasikan bahwa snapshot otomatis akan dibuat berkala saat pengetikan aktif berlangsung.

### 2.4 Preview & Restoration (Rollback)
- **REQ-3.1 (Read-Only Preview):**
  `WHEN` mahasiswa memilih salah satu entri riwayat dari daftar revisi, `THE` sistem `SHALL` memuat pratinjau konten revisi tersebut dalam status baca saja (*read-only*) tanpa mengubah dokumen draf aktif.
- **REQ-3.2 (Rollback Confirmation Modal):**
  `WHEN` mahasiswa menekan tombol "Pulihkan Versi Ini", `THE` sistem `SHALL` menampilkan dialog konfirmasi peringatan sebelum melakukan penimpaan konten.
- **REQ-3.3 (Safety Snapshot Before Restore):**
  `WHEN` proses pemulihan (*rollback*) dikonfirmasi, `THE` sistem `SHALL` terlebih dahulu membuat snapshot otomatis penanda ("Sebelum Pemulihan ke [Nama Versi]") untuk kondisi draf saat ini, lalu menimpa konten chapter dengan konten revisi target.
- **REQ-3.4 (Editor Synchronous Refresh):**
  `WHEN` pemulihan berhasil di sisi server, `THE` sistem `SHALL` menyinkronkan kembali instans editor Tiptap di browser dan menampilkan notifikasi sukses.

### 2.5 Security & Tenant Isolation
- **REQ-4.1 (Authorization Gate):**
  `WHILE` memproses permintaan pembuatan, pembacaan, dan pemulihan revisi, `THE` sistem `SHALL` memvalidasi bahwa chapter dan seluruh revisinya berada di dalam project milik user yang sedang terotentikasi.
- **REQ-4.2 (Cascading Deletion):**
  `WHEN` suatu Chapter atau Project dihapus, `THE` sistem `SHALL` menghapus seluruh relasi `chapter_revisions` terkait secara atomik (*cascade delete* pada tingkat basis data).

---

## 3. Non-Functional Requirements
- **NFR-1 (Performance):** Waktu respon endpoint pembuatan dan pengambilan riwayat revisi maksimal 200ms pada beban standar.
- **NFR-2 (Storage Footprint):** Penyimpanan Tiptap JSON disimpan dalam format terkompresi/native JSONB, dipagari dengan cap 50 auto-snapshot per bab untuk mencegah pemborosan storage basis data.
- **NFR-3 (UX Smoothness):** Pembuatan auto-snapshot di latar belakang tidak boleh membekukan antarmuka pengetikan (*zero UI thread blocking*).

---

## 4. Constraints & Guardrails
- Tetap mematuhi filosofi *Single Modular Monolith* Laravel 13 + Inertia (Vue 3).
- Model Eloquent `ChapterRevision` strictly belongs to `Chapter`.
- Tidak ada dependensi tambahan yang tidak perlu (*Lazy Senior Dev Principle*).

---

## 5. Success Criteria
1. Pengujian automated (Pest) memverifikasi aturan interval 5 menit, batasan cap 50 snapshot, kekebalan checkpoint manual dari rotasi FIFO, dan integritas rollback.
2. Seluruh 55 pengujian existing tetap lolos tanpa regresi.

---

## 6. Glossary
- **Chapter Document**: Dokumen terstruktur per bab skripsi (Bab I, Bab II, dst).
- **Chapter Revision**: Snapshot titik waktu (*point-in-time snapshot*) dari konten Tiptap suatu Chapter Document.
- **Auto Snapshot**: Snapshot otomatis setiap 5 menit pengetikan aktif.
- **Manual Checkpoint**: Snapshot bernama yang dibuat sengaja oleh pengguna.

---

## 7. Quality & EARS Checklist
- [x] **Quality Gate**: Kebutuhan spesifik, dapat diuji (*testable*), dan tidak ambigu.
- [x] **Completeness Gate**: Menangani skenario normal, pengecualian (*edge cases*), dan batasan rotasi.
- [x] **Traceability Gate**: Setiap REQ memiliki ID unik (REQ-1.1 s/d REQ-4.2).
- [x] **EARS Compliance**: Seluruh klausul acceptance menggunakan sintaksis EARS (`WHEN`, `WHILE`, `IF...THEN`).
