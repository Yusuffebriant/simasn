<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot INDIVIDUAL pegawai per periode + riwayat perubahan antar periode.
     *
     * Kenapa tabel baru: tabel pegawai selalu ditimpa import bulan berikutnya
     * (mode ganti total) dan rekap_snapshot_data hanya menyimpan angka agregat,
     * jadi keduanya tidak bisa menjawab "pegawai X dulu jabatannya apa".
     * Tabel lama (rekap_snapshots, rekap_snapshot_data, import_perubahan)
     * TIDAK diubah.
     *
     *  - pegawai_snapshot_periode : satu baris per periode (metadata, versi, audit koreksi)
     *  - pegawai_snapshots        : satu baris per pegawai per periode (nilai atribut saat itu)
     *  - pegawai_perubahan_riwayat: satu baris per perubahan atribut antara dua
     *                               periode BERURUTAN yang punya snapshot
     */
    public function up(): void
    {
        Schema::create('pegawai_snapshot_periode', function (Blueprint $table) {
            $table->id();
            $table->string('periode', 7)->unique();

            // Sumber data: import yang membentuk snapshot ini. Batch yang dihapus
            // tidak boleh ikut menghapus snapshot, jadi nullOnDelete.
            $table->foreignId('import_batch_id')->nullable()
                ->constrained('import_batches')->nullOnDelete();
            $table->string('sumber', 20)->default('import'); // import | perintah

            $table->unsignedInteger('jumlah_pegawai')->default(0);
            $table->unsignedInteger('jumlah_identitas_valid')->default(0);
            // NIP kosong/tidak standar/NIK pengganti/duplikat/tanpa identitas.
            $table->unsignedInteger('jumlah_perlu_verifikasi')->default(0);
            $table->unsignedInteger('jumlah_tanpa_identitas')->default(0);
            $table->unsignedInteger('jumlah_duplikat')->default(0);

            // Sidik isi snapshot: impor ulang file yang sama (hash sama) tidak
            // menambah versi dan tidak menghitung ulang perubahan.
            $table->string('hash_isi', 40);
            $table->unsignedInteger('versi')->default(1);
            // Daftar koreksi: [{versi, waktu, sumber, import_batch_id, jumlah_sebelum, jumlah_sesudah}]
            $table->json('riwayat')->nullable();

            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('pegawai_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('periode', 7);

            // Identitas pencocokan antarperiode: 'NIP:<18 digit>' atau 'NIK:<16 digit>'.
            // NULL = tidak bisa dicocokkan (tanpa identitas / duplikat).
            $table->string('kunci', 40)->nullable();
            // nip | nip_tidak_standar | nik | duplikat | tanpa_identitas
            $table->string('status_identitas', 20);
            $table->string('kunci_asal', 40)->nullable(); // kunci sebelum ditandai duplikat

            $table->unsignedBigInteger('pegawai_id')->nullable(); // bukan FK: baris pegawai ditimpa tiap import
            $table->string('nip')->nullable();
            $table->string('nik', 32)->nullable();
            $table->string('nama');

            $table->string('instansi')->nullable();
            $table->string('unit')->nullable();
            $table->string('sub_unit')->nullable();

            $table->string('kategori_jabatan', 12)->nullable(); // JFT | JFU | STRUKTURAL (turunan)
            $table->string('jenis_kedudukan', 20)->nullable();
            $table->string('jabatan')->nullable();
            $table->string('eselon', 20)->nullable();

            $table->string('golongan', 20)->nullable();
            $table->date('tmt_pangkat')->nullable();

            $table->string('pendidikan', 60)->nullable();
            $table->string('agama', 60)->nullable();
            $table->string('jenis_kelamin', 1)->nullable();
            $table->date('tanggal_lahir')->nullable();

            $table->string('status_kepegawaian', 20)->nullable();
            $table->string('status_lainnya', 60)->nullable();
            $table->string('status_aktif', 20)->nullable();
            $table->date('tanggal_pensiun')->nullable();
            $table->date('tanggal_selesai_kontrak')->nullable();

            $table->foreignId('import_batch_id')->nullable()
                ->constrained('import_batches')->nullOnDelete();
            $table->timestamp('dibuat_pada')->nullable();

            $table->unique(['periode', 'kunci']);
            $table->index('periode');
            $table->index(['periode', 'nik']);
        });

        Schema::create('pegawai_perubahan_riwayat', function (Blueprint $table) {
            $table->id();

            // Dua periode BERURUTAN yang sama-sama punya snapshot (bukan harus bulan berurutan:
            // kalau bulan di antaranya tidak ada, bulan_terlewati > 0).
            $table->string('periode_dari', 7);
            $table->string('periode_ke', 7);
            $table->unsignedSmallInteger('bulan_terlewati')->default(0);

            $table->string('kunci', 40);
            $table->string('kunci_sebelumnya', 40)->nullable(); // terisi kalau dicocokkan lewat NIK
            $table->string('nip')->nullable();
            $table->string('nama');
            $table->string('instansi')->nullable();
            $table->string('unit')->nullable();

            // pangkat_golongan | jabatan | unit_kerja | pendidikan | status_kepegawaian |
            // identitas | data_pribadi | keberadaan
            $table->string('kategori', 24);
            $table->string('atribut', 30); // '_keberadaan' untuk tidak_ditemukan / muncul
            // ubah | terisi | dikosongkan | tidak_ditemukan | muncul
            $table->string('jenis', 20);
            $table->string('nilai_sebelum', 500)->nullable();
            $table->string('nilai_sesudah', 500)->nullable();

            // Hanya diisi kalau SUMBER DATA menyediakannya (mis. PANGKAT_TMT).
            // Bukan tanggal impor dan bukan tanggal snapshot.
            $table->date('tanggal_efektif')->nullable();

            $table->boolean('perlu_verifikasi')->default(false);
            $table->string('alasan_verifikasi', 200)->nullable();

            $table->foreignId('import_batch_id')->nullable()
                ->constrained('import_batches')->nullOnDelete();
            $table->timestamp('dihitung_pada')->nullable();
            $table->timestamps();

            // Penjaga ganda: satu atribut satu pegawai satu pasangan periode hanya sekali.
            $table->unique(['periode_dari', 'periode_ke', 'kunci', 'atribut'], 'riwayat_unik');
            $table->index(['periode_dari', 'periode_ke']);
            $table->index('kunci');
            $table->index('kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawai_perubahan_riwayat');
        Schema::dropIfExists('pegawai_snapshots');
        Schema::dropIfExists('pegawai_snapshot_periode');
    }
};