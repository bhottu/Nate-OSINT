<?php

namespace App\Services\SocialAccountCorrelation;

class EvidenceScorer
{
    public function score(array $evidence): array
    {
        $weights = ['DIRECT CROSS-LINK' => 50, 'WEBSITE CROSS-LINK' => 30, 'MATCHING PUBLIC CONTACT' => 30, 'EXACT USERNAME' => 20, 'STRONG USERNAME VARIATION' => 15, 'MATCHING BIO' => 15, 'MATCHING DISPLAY NAME' => 10, 'SIMILAR PROFILE IMAGE' => 10, 'MATCHING PUBLIC BUSINESS INFO' => 10, 'WEAK CONTEXTUAL SIMILARITY' => 5];
        $records = array_values(array_filter($evidence, fn ($item) => is_array($item) && ! empty($item['source_url'])));
        $unique = array_values(array_unique(array_map(fn ($item) => is_array($item) ? $item['type'] : '', $records)));
        $score = min(100, array_sum(array_map(fn ($item) => $weights[$item] ?? 0, $unique)));
        $hasExplicitLink = collect($records)->contains(fn ($item) => $item['type'] === 'DIRECT CROSS-LINK' && ! empty($item['target_url']) && ! empty($item['source_platform']) && ! empty($item['target_platform']));
        $confidence = $hasExplicitLink ? 'CONFIRMED LINK' : ($score >= 45 ? 'STRONG CORRELATION' : ($score >= 15 ? 'POSSIBLE MATCH' : 'INSUFFICIENT EVIDENCE'));

        return ['score' => $score, 'confidence' => $confidence, 'evidence' => $unique, 'evidence_records' => $records];
    }
}
