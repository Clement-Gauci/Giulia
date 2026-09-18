<?php
namespace App\Contact\AntiSpam;

final readonly class SubmissionVerdict
{
    public function __construct(
        public Decision $decision,
        /** Motif technique, destiné au journal — jamais affiché au visiteur. */
        public string $reason = 'ok',
    ) {}
}
