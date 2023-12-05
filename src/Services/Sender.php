<?php

namespace LucasWPL\EmissorCte\Services;

use LucasWPL\EmissorCte\Traits\CTeCertificateToolsTrait;

class Sender
{
    use CTeCertificateToolsTrait;

    public function send(string $xml): string
    {
        return $this->tools->sefazEnviaCTe($xml);
    }
}