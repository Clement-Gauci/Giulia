<?php
namespace App\Contact\AntiSpam;

/**
 * Jeton horodaté et signé, posé dans le formulaire au rendu et revérifié à la
 * soumission. Il prouve trois choses : que la page a bien été servie par ce
 * site, quand elle l'a été, et — via le nom de leurre qu'il détermine — quel
 * champ piège attendre. Un POST direct, sans passer par la page, échoue.
 */
final readonly class FormSignature
{
    /** Un humain ne remplit pas le formulaire en moins de trois secondes. */
    private const int MIN_DELAY = 3;
    /** Au-delà, la page est périmée : on préfère la faire recharger. */
    private const int MAX_AGE = 7_200;

    public function __construct(private string $secret) {}

    public function issue(?int $now = null): string
    {
        $issuedAt = $now ?? time();

        return $issuedAt.'.'.$this->sign($issuedAt);
    }

    public function verify(string $token, ?int $now = null): TokenVerdict
    {
        $now ??= time();
        $parts = explode('.', $token);
        if (2 !== \count($parts) || 1 !== preg_match('~^\d{1,12}$~', $parts[0])) {
            return TokenVerdict::Invalid;
        }

        $issuedAt = (int) $parts[0];
        if (!hash_equals($this->sign($issuedAt), $parts[1])) {
            return TokenVerdict::Invalid;
        }

        $age = $now - $issuedAt;

        return match (true) {
            $age < self::MIN_DELAY => TokenVerdict::TooFast,
            $age > self::MAX_AGE => TokenVerdict::Expired,
            default => TokenVerdict::Ok,
        };
    }

    /**
     * Nom du champ leurre, déduit du jour du jeton : imprévisible pour un bot,
     * propre à ce site, renouvelé chaque jour — et cohérent même si la page a
     * été ouverte avant minuit et envoyée après.
     */
    public function honeypotFieldFor(string $token): string
    {
        $issuedAt = (int) (explode('.', $token)[0] ?? 0);
        $day = intdiv($issuedAt, 86_400);

        return 'site_'.substr(hash_hmac('sha256', 'honeypot:'.$day, $this->secret), 0, 8);
    }

    private function sign(int $issuedAt): string
    {
        return hash_hmac('sha256', 'form:'.$issuedAt, $this->secret);
    }
}
