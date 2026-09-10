<?php

namespace App\Services\SocialAccountCorrelation;

class InvalidSocialIdentifierDetector
{
    public function reason(string $value): ?string
    {
        $candidate = trim($value);
        $lower = strtolower($candidate);
        $technicalTokens = ['charset=', 'base64,', 'text/css', 'text/html', 'application/', 'data:', 'javascript:', 'style=', 'script', 'display:', 'position:', 'content:', 'font-', 'background:'];
        foreach ($technicalTokens as $token) {
            if (str_contains($lower, $token)) {
                return 'technical_html_css_data';
            }
        }
        if (preg_match('/^@?(?:css|media|import|font-face|keyframes|supports|layer)$/i', $candidate)) {
            return 'reserved_css_token';
        }
        if (preg_match('/^[a-z0-9+\/=]{24,}$/i', $candidate) && preg_match('/[+\/=]/', $candidate)) {
            return 'encoded_payload';
        }
        if (preg_match('/^[a-f0-9]{32,}$/i', $candidate)) {
            return 'hash_or_uuid';
        }

        return null;
    }
}
