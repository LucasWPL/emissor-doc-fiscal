<?php

namespace LucasWPL\EmissorDocFiscal\Services;

use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Tools;

class CommonService
{
    public string $config;

    private Tools $tools;

    private Certificate $certificate;

    public function __construct(string $configFilePath, string $certificateFilePath, string $certificatePassword)
    {
        $configJson = json_decode(file_get_contents($configFilePath));
 
        // temp fix
        $configJson->versao = '4.00';
        $configJson->tpAmb = (int) $configJson->tpAmb;
        $configJson->schemes = '';

        $this->config = json_encode($configJson);

        $this->certificate = Certificate::readPfx(file_get_contents($certificateFilePath), $certificatePassword);

        $this->tools = new Tools($this->config, $this->certificate);
    }


    public function getCadastro(string $UF, string $CNPJ): object 
    {
        return (new Standardize($this->tools->sefazCadastro($UF, $CNPJ)))->toStd();
    }
}