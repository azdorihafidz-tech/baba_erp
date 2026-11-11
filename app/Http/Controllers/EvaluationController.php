<?php

namespace App\Http\Controllers;

use App\Enums\PredikatEvaluasi;
use App\Http\Requests\EvaluationPeriodRequest;
use App\Models\Cabang;
use App\Models\Evaluation;
use App\Models\EvaluationAspect;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationReviewer;
use App\Models\EvaluationScore;
use App\Models\Karyawan;
use App\Models\User;
use App\Notifications\EvaluasiNotification;
use App\Services\EvaluationService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
    public function __construct(private EvaluationService $evaluationService) {}

    /**
     * Daftar periode evaluasi
     */
    public function periodIndex(Request $request)
    {
        $authUser = auth()->user();
        $query = EvaluationPeriod::with('cabang')
            ->withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->withCount('evaluations');

        if (!$authUser->canAccessAllBranches()) {
            $query->where('cabang_id', session('active_cabang_id'));
        } elseif ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        // Search — TAMBAHAN, tidak mengganti filter cabang di atas
        if ($request->filled('search')) {
            $query->where('nama_periode', 'like', '%' . $request->search . '%');
        }

        $periods = $query->orderByDesc('tanggal_mulai')->paginate(15)->withQueryString();
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        return view('evaluasi.periods', compact('periods', 'cabangs', 'authUser'));
    }

    /**
     * Form buat periode baru
     */
    public function periodCreate()
    {
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();
        return view('evaluasi.period-create', compact('cabangs'));
    }

    /**
     * Simpan periode baru
     */
    public function periodStore(EvaluationPeriodRequest $request)
    {
        $data = $request->validated();
        $data['status']     = 'draft';
        $data['created_by'] = auth()->id();

        $period = EvaluationPeriod::create($data);

        return redirect()->route('evaluasi.periods')
            ->with('success', "Periode penilaian \"{$period->nama_periode}\" berhasil dibuat.");
    }

    /**
     * Buka periode: status draft → dibuka, buat Evaluation untuk semua karyawan aktif
     */
    public function periodOpen(EvaluationPeriod $period)
    {
        if ($period->status->value !== 'draft') {
            return back()->with('error', 'Periode ini tidak bisa dibuka.');
        }

        DB::transaction(function () use ($period) {
            $period->update(['status' => 'dibuka']);

            $karyawans = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
                ->where('cabang_id', $period->cabang_id)
                ->where('status', 'aktif')
                ->get();

            // Kumpulkan semua user_id karyawan di cabang ini untuk rekan kerja
            $allUserIds = $karyawans->pluck('user_id')->filter()->values();

            foreach ($karyawans as $karyawan) {
                $evaluation = Evaluation::firstOrCreate(
                    ['evaluation_period_id' => $period->id, 'karyawan_id' => $karyawan->id],
                    [
                        'cabang_id' => $period->cabang_id,
                        'status'    => 'proses',
                    ]
                );

                // Assign atasan langsung sebagai reviewer jika ada
                if ($karyawan->atasan_id) {
                    $atasanUser = $karyawan->atasan?->user_id;
                    if ($atasanUser) {
                        EvaluationReviewer::updateOrCreate(
                            ['evaluation_id' => $evaluation->id, 'tipe_reviewer' => 'atasan'],
                            ['reviewer_id' => $atasanUser, 'bobot_reviewer_persen' => 50, 'status' => 'belum_isi']
                        );
                    }
                }

                // Self assessment
                if ($karyawan->user_id) {
                    EvaluationReviewer::updateOrCreate(
                        ['evaluation_id' => $evaluation->id, 'tipe_reviewer' => 'self_assessment'],
                        ['reviewer_id' => $karyawan->user_id, 'bobot_reviewer_persen' => 20, 'status' => 'belum_isi']
                    );
                }

                // Assign 2 rekan kerja secara acak (selain dirinya sendiri)
                $rekanIds = $allUserIds
                    ->reject(fn($uid) => $uid === $karyawan->user_id)
                    ->shuffle()
                    ->take(2);

                if ($rekanIds->count() > 0) {
                    $bobotRekan = round(30 / $rekanIds->count(), 2);
                    foreach ($rekanIds as $rekanUserId) {
                        EvaluationReviewer::updateOrCreate(
                            ['evaluation_id' => $evaluation->id, 'reviewer_id' => $rekanUserId, 'tipe_reviewer' => 'rekan_kerja'],
                            ['bobot_reviewer_persen' => $bobotRekan, 'status' => 'belum_isi']
                        );
                    }
                }
            }
        });

        // Notifikasi ke semua user di cabang
        $recipients = NotificationService::getUsersByCabang($period->cabang_id);
        NotificationService::send($recipients, new EvaluasiNotification($period, 'dibuka'));

        return back()->with('success', 'Periode penilaian berhasil dibuka dan evaluasi karyawan telah dibuat.');
    }

    /**
     * Tutup periode: dibuka → ditutup
     */
    public function periodClose(EvaluationPeriod $period)
    {
        if ($period->status->value !== 'dibuka') {
            return back()->with('error', 'Periode ini tidak bisa ditutup.');
        }

        $period->update(['status' => 'ditutup']);

        return back()->with('success', 'Periode penilaian berhasil ditutup.');
    }

    /**
     * Finalize semua evaluasi di periode
     */
    public function periodFinalize(EvaluationPeriod $period)
    {
        if (!in_array($period->status->value, ['dibuka', 'ditutup'])) {
            return back()->with('error', 'Periode ini tidak bisa di-finalize.');
        }

        DB::transaction(function () use ($period) {
            // Hitung skor untuk semua evaluasi yang belum selesai
            $evaluations = $period->evaluations()->where('status', '!=', 'final')->get();
            foreach ($evaluations as $evaluation) {
                $allFilled = $evaluation->reviewers()->count() > 0
                    && $evaluation->reviewers()->where('status', 'sudah_isi')->count() > 0;

                if ($allFilled) {
                    $this->evaluationService->hitungSkor($evaluation);
                }

                $evaluation->update([
                    'status'       => 'final',
                    'finalized_by' => auth()->id(),
                    'finalized_at' => now(),
                ]);
            }

            $period->update(['status' => 'final']);
        });

        // Notifikasi ke masing-masing karyawan yang dinilai
        $finalEvals = $period->evaluations()->with(['karyawan.user', 'summaries'])->whereNotNull('skor_akhir')->get();
        foreach ($finalEvals as $eval) {
            if ($eval->karyawan?->user_id) {
                $karyawanUser = \App\Models\User::find($eval->karyawan->user_id);
                if ($karyawanUser) {
                    NotificationService::send(collect([$karyawanUser]), new EvaluasiNotification(
                        $period,
                        'hasil_final',
                        $eval->skor_akhir,
                        $eval->predikat?->value ?? null
                    ));
                }
            }
        }

        return back()->with('success', 'Semua evaluasi pada periode ini telah difinalisasi.');
    }

    /**
     * Daftar penilaian yang perlu diisi oleh user login (sebagai reviewer)
     */
    public function myReviews()
    {
        $authUser = auth()->user();

        $reviewers = EvaluationReviewer::with([
            'evaluation.karyawan',
            'evaluation.period',
        ])
        ->where('reviewer_id', $authUser->id)
        ->whereHas('evaluation.period', function ($q) {
            $q->whereIn('status', ['dibuka', 'ditutup']);
        })
        ->orderByRaw("FIELD(status, 'belum_isi', 'sudah_isi')")
        ->orderBy('created_at', 'desc')
        ->get();

        return view('evaluasi.my-reviews', compact('reviewers'));
    }

    /**
     * Detail periode: semua evaluasi + status reviewer
     */
    public function periodDetail(EvaluationPeriod $period)
    {
        $evaluations = Evaluation::with([
            'karyawan',
            'reviewers.reviewer',
        ])
        ->withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
        ->where('evaluation_period_id', $period->id)
        ->orderBy('status')
        ->get();

        $cabangUsers = User::whereHas('cabangs', function ($q) use ($period) {
            $q->where('cabangs.id', $period->cabang_id);
        })->orderBy('name')->get();

        return view('evaluasi.period-detail', compact('period', 'evaluations', 'cabangUsers'));
    }

    /**
     * Tambah rekan kerja reviewer secara manual (oleh manajer)
     */
    public function assignRekanKerja(Request $request, Evaluation $evaluation)
    {
        $request->validate([
            'reviewer_id' => ['required', 'exists:users,id'],
        ]);

        // Cek tidak menilai diri sendiri
        if ($evaluation->karyawan?->user_id == $request->reviewer_id) {
            return back()->with('error', 'Karyawan tidak bisa menilai dirinya sendiri sebagai rekan kerja.');
        }

        // Hitung bobot (bagi rata 30% antar rekan kerja yang ada)
        $existingRekan = $evaluation->reviewers()->where('tipe_reviewer', 'rekan_kerja')->count();
        $newTotal      = $existingRekan + 1;
        $bobotRekan    = $newTotal > 0 ? round(30 / $newTotal, 2) : 30;

        // Update bobot rekan yang sudah ada
        $evaluation->reviewers()->where('tipe_reviewer', 'rekan_kerja')
            ->update(['bobot_reviewer_persen' => $bobotRekan]);

        EvaluationReviewer::updateOrCreate(
            ['evaluation_id' => $evaluation->id, 'reviewer_id' => $request->reviewer_id, 'tipe_reviewer' => 'rekan_kerja'],
            ['bobot_reviewer_persen' => $bobotRekan, 'status' => 'belum_isi']
        );

        // Kirim notifikasi ke reviewer baru
        $newReviewer = User::find($request->reviewer_id);
        if ($newReviewer && $evaluation->period) {
            NotificationService::send(collect([$newReviewer]), new EvaluasiNotification($evaluation->period, 'ditugaskan'));
        }

        return back()->with('success', 'Rekan kerja berhasil ditambahkan sebagai penilai.');
    }

    /**
     * Form isi penilaian untuk reviewer
     */
    public function formPenilaian(Evaluation $evaluation)
    {
        $authUser = auth()->user();

        // Cari reviewer record untuk user ini
        $reviewer = EvaluationReviewer::where('evaluation_id', $evaluation->id)
            ->where('reviewer_id', $authUser->id)
            ->first();

        if (!$reviewer) {
            return redirect()->route('evaluasi.periods')
                ->with('error', 'Anda tidak ditugaskan untuk menilai karyawan ini.');
        }

        if ($reviewer->status === 'sudah_isi') {
            return redirect()->route('evaluasi.result', $evaluation)
                ->with('warning', 'Anda sudah mengisi penilaian ini.');
        }

        $aspects  = EvaluationAspect::aktif()->get();
        $evaluation->load(['karyawan', 'period']);

        return view('evaluasi.form', compact('evaluation', 'reviewer', 'aspects'));
    }

    /**
     * Submit penilaian dari reviewer
     */
    public function submitPenilaian(Request $request, Evaluation $evaluation)
    {
        $authUser = auth()->user();

        $reviewer = EvaluationReviewer::where('evaluation_id', $evaluation->id)
            ->where('reviewer_id', $authUser->id)
            ->first();

        if (!$reviewer) {
            return redirect()->route('evaluasi.periods')
                ->with('error', 'Anda tidak ditugaskan untuk menilai karyawan ini.');
        }

        $request->validate([
            'scores'             => ['required', 'array'],
            'scores.*.aspect_id' => ['required', 'exists:evaluation_aspects,id'],
            'scores.*.skor'      => ['required', 'integer', 'min:1', 'max:5'],
            'scores.*.komentar'  => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $reviewer, $evaluation) {
            // Hapus skor lama jika ada
            $reviewer->scores()->delete();

            // Simpan skor baru
            foreach ($request->scores as $scoreData) {
                EvaluationScore::create([
                    'evaluation_reviewer_id' => $reviewer->id,
                    'evaluation_aspect_id'   => $scoreData['aspect_id'],
                    'skor'                   => $scoreData['skor'],
                    'komentar'               => $scoreData['komentar'] ?? null,
                ]);
            }

            // Update status reviewer
            $reviewer->update([
                'status'       => 'sudah_isi',
                'submitted_at' => now(),
            ]);

            // Cek apakah semua reviewer sudah mengisi
            $totalReviewers    = $evaluation->reviewers()->count();
            $filledReviewers   = $evaluation->reviewers()->where('status', 'sudah_isi')->count();

            if ($totalReviewers > 0 && $totalReviewers === $filledReviewers) {
                $this->evaluationService->hitungSkor($evaluation);

                // Notifikasi ke Manajer Cabang bahwa semua penilai sudah selesai
                $manajer = NotificationService::getManajerCabang($evaluation->cabang_id);
                $period  = $evaluation->period;
                if ($period) {
                    NotificationService::send($manajer, new EvaluasiNotification($period, 'semua_selesai'));
                }
            }
        });

        return redirect()->route('evaluasi.my-reviews')
            ->with('success', 'Penilaian berhasil dikirim. Terima kasih!');
    }

    /**
     * Hasil evaluasi 1 karyawan
     */
    public function result(Evaluation $evaluation)
    {
        $authUser = auth()->user();
        $isSelf   = $evaluation->karyawan?->user_id === $authUser->id;
        $isReviewer = EvaluationReviewer::where('evaluation_id', $evaluation->id)
            ->where('reviewer_id', $authUser->id)
            ->exists();

        abort_unless($authUser->can('evaluasi.view') || $isSelf || $isReviewer, 403);

        $evaluation->load([
            'karyawan', 'period', 'summaries.aspect',
            'reviewers.reviewer',
        ]);

        $aspects = EvaluationAspect::aktif()->get();
        $jumlahRekan = $evaluation->reviewers->where('tipe_reviewer.value', 'rekan_kerja')->count();

        return view('evaluasi.result', compact('evaluation', 'aspects', 'jumlahRekan'));
    }

    /**
     * Ranking karyawan di cabang untuk periode tertentu
     */
    public function ranking(EvaluationPeriod $period)
    {
        $evaluations = Evaluation::with('karyawan')
            ->withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('evaluation_period_id', $period->id)
            ->whereNotNull('skor_akhir')
            ->orderByDesc('skor_akhir')
            ->get();

        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        return view('evaluasi.ranking', compact('period', 'evaluations', 'cabangs'));
    }

    /**
     * Riwayat evaluasi 1 karyawan lintas periode
     */
    public function history(Karyawan $karyawan)
    {
        $authUser = auth()->user();
        $isSelf   = $karyawan->user_id === $authUser->id;

        abort_unless($authUser->can('evaluasi.view') || $isSelf, 403);

        $evaluations = Evaluation::with('period')
            ->withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('karyawan_id', $karyawan->id)
            ->orderBy('created_at')
            ->get();

        $karyawans = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')
            ->get();

        return view('evaluasi.history', compact('karyawan', 'evaluations', 'karyawans'));
    }
}
