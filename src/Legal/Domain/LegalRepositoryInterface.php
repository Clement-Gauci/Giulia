<?php
namespace App\Legal\Domain;

interface LegalRepositoryInterface
{
    public function get(): LegalNotice;
}
