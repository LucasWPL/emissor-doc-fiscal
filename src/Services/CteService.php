<?php

namespace LucasWPL\EmissorCte\Services;

use NFePHP\Common\Certificate;
use NFePHP\CTe\MakeCTe;
use NFePHP\CTe\Tools;

class CteService
{
    public string $config;

    private Tools $tools;
    private MakeCTe $cte;
    private Certificate $certificate;

    public function __construct(string $configFilePath, string $certificateFilePath, string $certificatePassword)
    {
        $this->config = file_get_contents($configFilePath);
        $this->certificate = Certificate::readPfx(file_get_contents($certificateFilePath), $certificatePassword);

        $this->tools = new Tools($this->config, $this->certificate);
        $this->tools->model('57');
    }

    public function generate()
    {
        // todo
    }

    public function sign(string $xml): string
    {
        return $this->tools->signCTe($xml);
    }

    public function send(string $xml): string
    {
        return $this->tools->sefazEnviaCTe($xml);
    }

    public function cancel(string $key, string $justification, string $protocol): string
    {
        return $this->tools->sefazCancela($key, $justification, $protocol);
    }
}
