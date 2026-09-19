<?php
namespace App\Account\Domain;

enum AttemptKind: string
{
    /** Une adresse non enregistrée (ou désactivée) a été soumise. */
    case UnknownEmail = 'unknown_email';

    /** Un code erroné a été soumis. */
    case WrongCode = 'wrong_code';

    /** Un code a été émis et envoyé. */
    case CodeSent = 'code_sent';
}
