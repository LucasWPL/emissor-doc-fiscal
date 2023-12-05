<?php

namespace LucasWPL\EmissorCte\Traits;

use NFePHP\Common\Certificate;
use NFePHP\CTe\MakeCTe;
use NFePHP\CTe\Tools;


trait CTeCertificateToolsTrait
{
    private Tools $tools;
    private MakeCTe $cte;
    private Certificate $certificate;

    private string $config;

    public function __construct(string $configFilePath, string $certificateFilePath, string $certificatePassword)
    {
        $this->config = file_get_contents($configFilePath);
        $this->certificate = Certificate::readPfx(file_get_contents($certificateFilePath), $certificatePassword);

        $this->tools = new Tools($this->config, $this->certificate);
        $this->tools->model('57');
    }
}
