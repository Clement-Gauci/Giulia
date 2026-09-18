<?php
namespace App\Contact\AntiSpam;

use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Enchaîne les filtres anti-spam et rend un verdict unique.
 *
 * L'ordre compte : on écarte d'abord ce qui ne coûte rien (jeton, leurre,
 * contenu), et seules les soumissions plausibles consomment le quota — un bot
 * ne peut donc pas épuiser le quota d'une vraie adresse.
 */
final readonly class SubmissionGuard
{
    public function __construct(
        private FormSignature $signature,
        private SpamScorer $scorer,
        private RateLimiterFactoryInterface $contactFormLimiter,
    ) {}

    public function inspect(
        string $token,
        string $honeypot,
        string $clientIp,
        string $name,
        string $email,
        string $message,
        ?int $now = null,
    ): SubmissionVerdict {
        $verdict = $this->signature->verify($token, $now);
        if (TokenVerdict::Expired === $verdict) {
            return new SubmissionVerdict(Decision::Retry, 'expired');
        }
        if (TokenVerdict::Invalid === $verdict) {
            return new SubmissionVerdict(Decision::Silence, 'token_invalid');
        }
        if (TokenVerdict::TooFast === $verdict) {
            return new SubmissionVerdict(Decision::Silence, 'too_fast');
        }

        if ('' !== trim($honeypot)) {
            return new SubmissionVerdict(Decision::Silence, 'honeypot');
        }

        $score = $this->scorer->score($name, $email, $message);
        if ($score >= SpamScorer::THRESHOLD) {
            return new SubmissionVerdict(Decision::Silence, 'score:'.$score);
        }

        if (!$this->contactFormLimiter->create($clientIp)->consume()->isAccepted()) {
            return new SubmissionVerdict(Decision::Silence, 'rate_limited');
        }

        return new SubmissionVerdict(Decision::Accept);
    }
}
