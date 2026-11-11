<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_aspects', function (Blueprint $table) {
            $table->id();
            $table->string('nama_aspek');
            $table->text('deskripsi')->nullable();
            $table->decimal('bobot_persen', 5, 2); // misal: 25.00
            $table->integer('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('evaluation_periods', function (Blueprint $table) {
            $table->id();
            $table->string('nama_periode'); // contoh: "Q1 2026"
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->date('deadline_pengisian');
            $table->enum('status', ['draft', 'dibuka', 'ditutup', 'final'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_period_id')->constrained('evaluation_periods')->cascadeOnDelete();
            $table->foreignId('karyawan_id')->constrained('karyawans')->cascadeOnDelete();
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->decimal('skor_akhir', 5, 4)->nullable();
            $table->enum('predikat', ['sangat_baik', 'baik', 'cukup', 'kurang', 'sangat_kurang'])->nullable();
            $table->text('catatan_manajer')->nullable();
            $table->enum('status', ['proses', 'selesai', 'final'])->default('proses');
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_period_id', 'karyawan_id']);
        });

        Schema::create('evaluation_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->enum('tipe_reviewer', ['atasan', 'rekan_kerja', 'self_assessment']);
            $table->decimal('bobot_reviewer_persen', 5, 2); // 50, 30, atau 20
            $table->enum('status', ['belum_isi', 'sudah_isi'])->default('belum_isi');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_reviewer_id')->constrained('evaluation_reviewers')->cascadeOnDelete();
            $table->foreignId('evaluation_aspect_id')->constrained('evaluation_aspects')->cascadeOnDelete();
            $table->tinyInteger('skor'); // 1-5
            $table->text('komentar')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_reviewer_id', 'evaluation_aspect_id'], 'eval_scores_reviewer_aspect_unique');
        });

        Schema::create('evaluation_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('evaluation_aspect_id')->constrained('evaluation_aspects')->cascadeOnDelete();
            $table->decimal('skor_atasan', 5, 4)->nullable();
            $table->decimal('skor_rekan_rata', 5, 4)->nullable();
            $table->decimal('skor_self', 5, 4)->nullable();
            $table->decimal('skor_tertimbang', 5, 4)->nullable(); // skor aspek setelah bobot penilai
            $table->decimal('skor_final', 5, 4)->nullable(); // skor_tertimbang * bobot_aspek
            $table->timestamps();

            $table->unique(['evaluation_id', 'evaluation_aspect_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_summaries');
        Schema::dropIfExists('evaluation_scores');
        Schema::dropIfExists('evaluation_reviewers');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('evaluation_periods');
        Schema::dropIfExists('evaluation_aspects');
    }
};
