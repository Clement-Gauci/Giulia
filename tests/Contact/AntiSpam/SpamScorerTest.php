<?php
namespace App\Tests\Contact\AntiSpam;

use App\Contact\AntiSpam\SpamScorer;
use PHPUnit\Framework\TestCase;

final class SpamScorerTest extends TestCase
{
    private SpamScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new SpamScorer();
    }

    public function test_an_ordinary_message_scores_zero(): void
    {
        $score = $this->scorer->score(
            'Marie Dupont',
            'marie@example.fr',
            "Bonjour, avez-vous une pizza sans gluten ? Merci d'avance.",
        );

        self::assertSame(0, $score);
    }

    public function test_a_single_link_is_barely_suspicious(): void
    {
        $score = $this->scorer->score(
            'Marie Dupont',
            'marie@example.fr',
            'Bonjour, voici le lieu de la soirée : https://maps.example/abc — merci !',
        );

        self::assertSame(1, $score);
    }

    public function test_two_links_raise_the_score(): void
    {
        $score = $this->scorer->score(
            'Marie Dupont',
            'marie@example.fr',
            'Voir https://un.example et http://deux.example pour les détails.',
        );

        self::assertSame(3, $score);
    }

    public function test_a_flood_of_links_scores_high(): void
    {
        $score = $this->scorer->score(
            'Marie Dupont',
            'marie@example.fr',
            'https://a.example https://b.example https://c.example https://d.example',
        );

        self::assertSame(6, $score);
    }

    public function test_bbcode_links_are_spam_grade(): void
    {
        $score = $this->scorer->score(
            'Marie',
            'marie@example.fr',
            'Bonjour [url=https://spam.example]cliquez ici[/url] cordialement',
        );

        // +4 pour le BBCode, +1 pour le lien qu'il contient.
        self::assertSame(5, $score);
    }

    public function test_a_message_in_a_non_latin_script_is_suspicious(): void
    {
        $score = $this->scorer->score(
            'Marie',
            'marie@example.fr',
            'Здравствуйте, предлагаем услуги продвижения сайта.',
        );

        self::assertSame(4, $score);
    }

    public function test_accented_french_is_not_mistaken_for_a_foreign_script(): void
    {
        $score = $this->scorer->score(
            'Éloïse Gaûthier',
            'eloise@example.fr',
            'Bonjour, ma fille est cœliaque — proposez-vous une pâte sans gluten ?',
        );

        self::assertSame(0, $score);
    }

    public function test_a_newline_in_the_name_is_a_header_injection_attempt(): void
    {
        $score = $this->scorer->score(
            "Marie\r\nBcc: victime@example.fr",
            'marie@example.fr',
            'Bonjour, une question rapide.',
        );

        self::assertGreaterThanOrEqual(SpamScorer::THRESHOLD, $score);
    }

    public function test_a_newline_in_the_email_is_a_header_injection_attempt(): void
    {
        $score = $this->scorer->score(
            'Marie',
            "marie@example.fr\nBcc: victime@example.fr",
            'Bonjour, une question rapide.',
        );

        self::assertGreaterThanOrEqual(SpamScorer::THRESHOLD, $score);
    }

    public function test_marketing_jargon_accumulates(): void
    {
        $score = $this->scorer->score(
            'John',
            'john@example.com',
            'We offer SEO backlinks to boost your ranking, guaranteed traffic.',
        );

        self::assertSame(4, $score);
    }

    public function test_a_message_that_merely_repeats_the_name_is_suspicious(): void
    {
        $score = $this->scorer->score(
            'Katrinapaype',
            'katrina@example.com',
            'Katrinapaype',
        );

        self::assertSame(3, $score);
    }

    public function test_a_real_customer_message_stays_below_the_threshold(): void
    {
        $score = $this->scorer->score(
            'Paul Martin',
            'paul.martin@example.fr',
            "Bonjour, je souhaite réserver pour 8 personnes samedi soir. "
            ."J'ai vu vos horaires ici : https://giulia-pizza-gorges.fr/horaires. Merci !",
        );

        self::assertLessThan(SpamScorer::THRESHOLD, $score);
    }
}

