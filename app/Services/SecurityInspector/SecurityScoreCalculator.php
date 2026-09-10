<?php

namespace App\Services\SecurityInspector;

class SecurityScoreCalculator
{
    public function calculate(array $checks): array
    {
        $score = collect($checks)->sum(fn (array $check) => $check['score'] ?? 0);
        $max = collect($checks)->sum(fn (array $check) => $check['max'] ?? 0);
        $score = $max ? (int) round($score / $max * 100) : 0;
        $grade = $score >= 90 ? 'A' : ($score >= 80 ? 'B' : ($score >= 70 ? 'C' : ($score >= 60 ? 'D' : 'F')));

        return ['value' => $score, 'grade' => $grade, 'breakdown' => $checks];
    }
}
