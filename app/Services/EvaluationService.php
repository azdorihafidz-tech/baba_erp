<?php

namespace App\Services;

use App\Enums\PredikatEvaluasi;
use App\Models\Evaluation;
use App\Models\EvaluationAspect;
use App\Models\EvaluationReviewer;
use App\Models\EvaluationSummary;
use Illuminate\Support\Facades\DB;

class EvaluationService
{
    /**
     * Hitung skor akhir untuk satu evaluasi
     */
    public function hitungSkor(Evaluation $evaluation): void
    {
        DB::transaction(function () use ($evaluation) {
            $aspects  = EvaluationAspect::aktif()->get();
            $reviewers = $evaluation->reviewers()->where('status', 'sudah_isi')->with('scores')->get();

            $atasanReviewer = $reviewers->where('tipe_reviewer', 'atasan')->first();
            $rekanReviewers = $reviewers->where('tipe_reviewer', 'rekan_kerja');
            $selfReviewer   = $reviewers->where('tipe_reviewer', 'self_assessment')->first();

            $skorAkhirTotal = 0;

            foreach ($aspects as $aspect) {
                $skorAtasan = $atasanReviewer
                    ? ($atasanReviewer->scores->where('evaluation_aspect_id', $aspect->id)->first()?->skor ?? 0)
                    : 0;

                if ($rekanReviewers->count() > 0) {
                    $skorRekanRata = $rekanReviewers->avg(function ($r) use ($aspect) {
                        return $r->scores->where('evaluation_aspect_id', $aspect->id)->first()?->skor ?? 0;
                    });
                } else {
                    $skorRekanRata = 0;
                }

                $skorSelf = $selfReviewer
                    ? ($selfReviewer->scores->where('evaluation_aspect_id', $aspect->id)->first()?->skor ?? 0)
                    : 0;

                // Bobot reviewer
                $bobotAtasan = $atasanReviewer ? ($atasanReviewer->bobot_reviewer_persen / 100) : 0.5;
                $bobotRekan  = $rekanReviewers->count() > 0 ? 0.3 : 0;
                $bobotSelf   = $selfReviewer ? 0.2 : 0;

                // Normalisasi bobot jika ada yang kosong
                $totalBobot = $bobotAtasan + $bobotRekan + $bobotSelf;
                if ($totalBobot > 0) {
                    $skorTertimbang = ($skorAtasan * $bobotAtasan
                        + $skorRekanRata * $bobotRekan
                        + $skorSelf * $bobotSelf) / $totalBobot;
                } else {
                    $skorTertimbang = 0;
                }

                $skorFinal = $skorTertimbang * ($aspect->bobot_persen / 100);
                $skorAkhirTotal += $skorFinal;

                EvaluationSummary::updateOrCreate(
                    ['evaluation_id' => $evaluation->id, 'evaluation_aspect_id' => $aspect->id],
                    [
                        'skor_atasan'     => $skorAtasan,
                        'skor_rekan_rata' => $skorRekanRata,
                        'skor_self'       => $skorSelf,
                        'skor_tertimbang' => $skorTertimbang,
                        'skor_final'      => $skorFinal,
                    ]
                );
            }

            $evaluation->update([
                'skor_akhir' => $skorAkhirTotal,
                'predikat'   => PredikatEvaluasi::fromSkor($skorAkhirTotal)->value,
                'status'     => 'selesai',
            ]);
        });
    }

    /**
     * Assign penilai ke evaluasi karyawan
     */
    public function assignReviewers(
        Evaluation $evaluation,
        int $atasanUserId,
        array $rekanUserIds,
        int $selfUserId
    ): void {
        // Atasan: bobot 50%
        EvaluationReviewer::updateOrCreate(
            ['evaluation_id' => $evaluation->id, 'tipe_reviewer' => 'atasan'],
            ['reviewer_id' => $atasanUserId, 'bobot_reviewer_persen' => 50, 'status' => 'belum_isi']
        );

        // Rekan kerja: bobot 30% dibagi rata
        $bobotRekan = count($rekanUserIds) > 0 ? round(30 / count($rekanUserIds), 2) : 0;
        foreach ($rekanUserIds as $rekanId) {
            EvaluationReviewer::updateOrCreate(
                ['evaluation_id' => $evaluation->id, 'reviewer_id' => $rekanId, 'tipe_reviewer' => 'rekan_kerja'],
                ['bobot_reviewer_persen' => $bobotRekan, 'status' => 'belum_isi']
            );
        }

        // Self assessment: bobot 20%
        EvaluationReviewer::updateOrCreate(
            ['evaluation_id' => $evaluation->id, 'tipe_reviewer' => 'self_assessment'],
            ['reviewer_id' => $selfUserId, 'bobot_reviewer_persen' => 20, 'status' => 'belum_isi']
        );
    }
}
