<?php
namespace App\Contact\Domain;

final readonly class ContactMessage
{
    public function __construct(
        private string $name,
        private string $email,
        private ?string $phone,
        private Subject $subject,
        private string $message,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-mail invalide.');
        }
        if (trim($message) === '') {
            throw new \InvalidArgumentException('Le message est obligatoire.');
        }
    }

    public function name(): string { return $this->name; }
    public function email(): string { return $this->email; }
    public function phone(): ?string { return $this->phone; }
    public function subject(): Subject { return $this->subject; }
    public function message(): string { return $this->message; }
}
