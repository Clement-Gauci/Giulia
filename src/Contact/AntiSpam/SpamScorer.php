<?php
namespace App\Contact\AntiSpam;

/**
 * Attribue un score de suspicion à une soumission du formulaire de contact.
 *
 * Volontairement cumulatif plutôt que couperet : un client qui colle un lien
 * Google Maps reste sous le seuil, un bot qui accumule les signaux le dépasse.
 * Classe pure, sans dépendance : les seuils se lisent dans les tests.
 */
final class SpamScorer
{
    /** Score à partir duquel la soumission est écartée. */
    public const int THRESHOLD = 5;

    /**
     * Vocabulaire quasi absent des messages d'une pizzerie de Gorges, et
     * omniprésent dans le spam. Comparé en minuscules, sur des mots entiers.
     */
    private const array JARGON = [
        'backlink', 'seo', 'référencement', 'ranking', 'crypto', 'bitcoin',
        'casino', 'viagra', 'cialis', 'escort', 'webcam', 'payday',
        'business proposal', 'dear sir', 'make money', 'work from home',
    ];

    public function score(string $name, string $email, string $message): int
    {
        return $this->headerInjectionScore($name.$email)
            + $this->linkScore($message)
            + $this->bbCodeScore($message)
            + $this->foreignScriptScore($name.' '.$message)
            + $this->jargonScore($message)
            + $this->echoedNameScore($name, $message);
    }

    /**
     * Un retour à la ligne dans un champ d'en-tête ne vient jamais d'un humain :
     * c'est une tentative d'injecter un Bcc dans l'e-mail. Rejet à lui seul.
     */
    private function headerInjectionScore(string $headerFields): int
    {
        return preg_match('~[\r\n]~', $headerFields) ? self::THRESHOLD + 4 : 0;
    }

    private function linkScore(string $message): int
    {
        $links = preg_match_all('~\bhttps?://~i', $message);

        return match (true) {
            $links >= 3 => 6,
            $links === 2 => 3,
            $links === 1 => 1,
            default => 0,
        };
    }

    /** Le BBCode n'a aucun sens dans un champ texte : c'est un robot de forum. */
    private function bbCodeScore(string $message): int
    {
        return preg_match('~\[/?(?:url|link|img)\b~i', $message) ? 4 : 0;
    }

    /**
     * Écritures non latines. Les accents français passent : seuls comptent les
     * alphabets qu'aucun client de la pizzeria n'utiliserait pour écrire ici.
     */
    private function foreignScriptScore(string $text): int
    {
        $foreign = preg_match_all(
            '~[\p{Cyrillic}\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Arabic}\p{Thai}\p{Hebrew}]~u',
            $text,
        );

        return $foreign >= 3 ? 4 : 0;
    }

    private function jargonScore(string $message): int
    {
        $haystack = mb_strtolower($message);
        $hits = 0;
        foreach (self::JARGON as $term) {
            if (preg_match('~\b'.preg_quote($term, '~').'~u', $haystack)) {
                ++$hits;
            }
        }

        return min($hits * 2, 4);
    }

    /** Message vide de sens qui recopie le nom : signature des bots d'inscription. */
    private function echoedNameScore(string $name, string $message): int
    {
        return '' !== trim($name) && mb_strtolower(trim($name)) === mb_strtolower(trim($message)) ? 3 : 0;
    }
}
