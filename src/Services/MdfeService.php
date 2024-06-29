<?php

namespace LucasWPL\EmissorDocFiscal\Services;

use NFePHP\Common\Certificate;
use NFePHP\MDFe\Tools;

class MdfeService
{
    public string $config;

    private Tools $tools;
    private Certificate $certificate;

    public function __construct(string $configFilePath, string $certificateFilePath, string $certificatePassword)
    {
        $this->config = file_get_contents($configFilePath);
        $this->certificate = Certificate::readPfx(file_get_contents($certificateFilePath), $certificatePassword);

        $this->tools = new Tools($this->config, $this->certificate);
    }

    public function sign(string $xml): string
    {
        return $this->tools->signMDFe($xml);
    }

    public function cancel(string $key, string $justification, string $protocol): string
    {
        return $this->tools->sefazCancela($key, $justification, $protocol);
    }

    public function close(string $key, string $protocol, string $state, string $city): string
    {
        return $this->tools->sefazEncerra($key, $protocol, $state, $city);
    }
}