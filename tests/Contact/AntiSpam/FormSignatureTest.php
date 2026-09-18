<?php
namespace App\Tests\Contact\AntiSpam;

use App\Contact\AntiSpam\FormSignature;
use App\Contact\AntiSpam\TokenVerdict;
use PHPUnit\Framework\TestCase;

final class FormSignatureTest extends TestCase
{
    private const int RENDERED_AT = 1_758_240_000; // 2025-09-19 00:00:00 UTC

    private FormSignature $signature;

    protected function setUp(): void
    {
        $this->signature = new FormSignature('un-secret-de-test');
    }

    public function test_a_token_filled_in_at_human_speed_is_accepted(): void
    {
        $token = $this->signature->issue(self::RENDERED_AT);

        self::assertSame(TokenVerdict::Ok, $this->signature->verify($token, self::RENDERED_AT + 30));
    }

    public function test_a_token_returned_instantly_is_too_fast_for_a_human(): void
    {
        $token = $this->signature->issue(self::RENDERED_AT);

        self::assertSame(TokenVerdict::TooFast, $this->signature->verify($token, self::RENDERED_AT + 1));
    }

    public function test_a_token_from_a_stale_page_has_expired(): void
    {
        $token = $this->signature->issue(self::RENDERED_AT);

        self::assertSame(TokenVerdict::Expired, $this->signature->verify($token, self::RENDERED_AT + 86_400));
    }

    public function test_a_forged_timestamp_invalidates_the_signature(): void
    {
        $token = $this->signature->issue(self::RENDERED_AT);
        [, $mac] = explode('.', $token);
        $forged = (self::RENDERED_AT - 600).'.'.$mac;

        self::assertSame(TokenVerdict::Invalid, $this->signature->verify($forged, self::RENDERED_AT + 30));
    }

    public function test_a_token_signed_with_another_secret_is_rejected(): void
    {
        $token = (new FormSignature('un-autre-secret'))->issue(self::RENDERED_AT);

        self::assertSame(TokenVerdict::Invalid, $this->signature->verify($token, self::RENDERED_AT + 30));
    }

    public function test_a_missing_token_is_rejected(): void
    {
        self::assertSame(TokenVerdict::Invalid, $this->signature->verify('', self::RENDERED_AT + 30));
    }

    public function test_a_garbled_token_is_rejected_without_crashing(): void
    {
        self::assertSame(TokenVerdict::Invalid, $this->signature->verify('nawak', self::RENDERED_AT + 30));
    }

    public function test_the_honeypot_name_is_a_valid_form_field_name(): void
    {
        $name = $this->signature->honeypotFieldFor($this->signature->issue(self::RENDERED_AT));

        self::assertMatchesRegularExpression('~^[a-z][a-z0-9_]{4,}$~', $name);
    }

    public function test_the_honeypot_name_is_stable_within_the_same_day(): void
    {
        $morning = $this->signature->honeypotFieldFor($this->signature->issue(self::RENDERED_AT));
        $evening = $this->signature->honeypotFieldFor($this->signature->issue(self::RENDERED_AT + 60_000));

        self::assertSame($morning, $evening);
    }

    public function test_the_honeypot_name_changes_from_one_day_to_the_next(): void
    {
        $today = $this->signature->honeypotFieldFor($this->signature->issue(self::RENDERED_AT));
        $tomorrow = $this->signature->honeypotFieldFor($this->signature->issue(self::RENDERED_AT + 86_400));

        self::assertNotSame($today, $tomorrow);
    }

    public function test_the_honeypot_name_is_specific_to_the_site_secret(): void
    {
        $here = $this->signature->honeypotFieldFor($this->signature->issue(self::RENDERED_AT));
        $elsewhere = (new FormSignature('ailleurs'))->honeypotFieldFor((new FormSignature('ailleurs'))->issue(self::RENDERED_AT));

        self::assertNotSame($here, $elsewhere);
    }

    public function test_a_page_rendered_before_midnight_keeps_its_own_honeypot_name(): void
    {
        // Le nom se déduit du jeton, pas de l'heure de la soumission : une page
        // ouverte à 23 h 59 et envoyée à 0 h 01 reste cohérente.
        $token = $this->signature->issue(self::RENDERED_AT - 60);

        self::assertSame(
            $this->signature->honeypotFieldFor($this->signature->issue(self::RENDERED_AT - 3_600)),
            $this->signature->honeypotFieldFor($token),
        );
    }
}
