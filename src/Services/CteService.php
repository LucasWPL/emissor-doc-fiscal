<?php

namespace LucasWPL\EmissorDocFiscal\Services;

use NFePHP\Common\Certificate;
use NFePHP\CTe\Common\Standardize;
use NFePHP\CTe\Complements;
use NFePHP\CTe\Tools;

class CteService
{
    public string $config;

    private Tools $tools;
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

    public function cce(string $key, array $data, int $seq = 1): string
    {
        return $this->tools->sefazCCe($key, $data, $seq);
    }

    public function saveResponse(string $filename, string $response): void
    {
        $stdCl = new Standardize($response);
        
        $std = $stdCl->toStd();
        $cStat = $std->infEvento->cStat;
        
        if ($cStat == '101' || $cStat == '135' || $cStat == '155') {
            $xml = Complements::toAuthorize($this->tools->lastRequest, $response);            
            file_put_contents($filename, $xml);
        }
    }
    
    public function fetchDfe(string $savePath = 'storage/dfe'): array
    {
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }

        $metadataPath = "{$savePath}/metadata.json";
        $ultNSU = 0;
        
        if (file_exists($metadataPath)) {
            $metadata = json_decode(file_get_contents($metadataPath), true);
            $ultNSU = $metadata['ultNSU'] ?? 0;
        }

        $processed = [];
        $loopCount = 0;
        $maxLoop = 50; // Limite de segurança para evitar timeout do PHP

        while ($loopCount < $maxLoop) {
            $loopCount++;
            
            try {
                $resp = $this->tools->sefazDistDFe($ultNSU);
            } catch (\Exception $e) {
                // Em caso de falha de comunicação, interrompe para tentar depois
                break;
            }
            
            $dom = new \DOMDocument();
            $dom->loadXML($resp);
            
            $cStatNode = $dom->getElementsByTagName('cStat')->item(0);
            $cStat = $cStatNode ? $cStatNode->nodeValue : '';
            
            if ($cStat == '138') { // Documentos localizados
                $ultNSUNode = $dom->getElementsByTagName('ultNSU')->item(0);
                $maxNSUNode = $dom->getElementsByTagName('maxNSU')->item(0);
                
                $ultNSUResp = $ultNSUNode ? $ultNSUNode->nodeValue : $ultNSU;
                $maxNSU = $maxNSUNode ? $maxNSUNode->nodeValue : $ultNSU;
                
                $lote = $dom->getElementsByTagName('loteDistDFeInt')->item(0);
                if ($lote) {
                    $docZips = $lote->getElementsByTagName('docZip');
                    foreach ($docZips as $docZip) {
                        if (!$docZip instanceof \DOMElement) continue;

                        $nsu = $docZip->getAttribute('NSU');
                        $schema = $docZip->getAttribute('schema');
                        $content = gzdecode(base64_decode($docZip->nodeValue));
                        
                        $type = $this->identifySchemaType($schema);
                        
                        $dir = "{$savePath}/{$type}";
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        
                        $filename = "{$nsu}.xml";
                        file_put_contents("{$dir}/{$filename}", $content);
                        
                        $processed[] = [
                            'nsu' => $nsu,
                            'schema' => $schema,
                            'type' => $type,
                            'file' => "{$dir}/{$filename}"
                        ];
                    }
                }
                
                // Atualiza o ponteiro
                $ultNSU = $ultNSUResp;

                // Salva o progresso atual
                $this->updateMetadata($savePath, $ultNSU, $maxNSU, $cStat);

                // Se já pegou tudo, para
                if ($ultNSU >= $maxNSU) {
                    break;
                }
            } else {
                $this->updateMetadata($savePath, $ultNSU, $ultNSU, $cStat);
                break;
            }

            // Pausa obrigatória para evitar Rejeição 656 (Consumo Indevido)
            sleep(2);
        }
        
        return $processed;
    }

    /**
     * Registra o evento de Prestação de Serviço em Desacordo.
     * Substitui o conceito de "Manifestação" da NFe.
     */
    public function manifest(string $key, int $tpEvento, string $justification = '', int $seq = 1): string
    {
        $config = json_decode($this->config);
        $uf = $config->siglaUF ?? 'RN';
        return $this->tools->sefazManifesta($key, $tpEvento, $justification, $seq, $uf);
    }

    private function identifySchemaType($schema): string 
    {
        if (strpos($schema, 'procCTe') !== false) return 'complete';
        if (strpos($schema, 'resCTe') !== false) return 'summary';
        if (strpos($schema, 'procEventoCTe') !== false) return 'event_complete';
        if (strpos($schema, 'resEvento') !== false) return 'event_summary';
        return 'unknown';
    }

    private function updateMetadata($savePath, $ultNSU, $maxNSU, $lastStatus): void
    {
        $currentMetadata = json_decode(file_get_contents("{$savePath}/metadata.json"), true);
        if (
            $currentMetadata['ultNSU'] == $ultNSU && 
            $currentMetadata['maxNSU'] == $maxNSU && 
            $currentMetadata['last_status'] == $lastStatus
        ) {
            return;
        }
        
        $metadata = [
            'ultNSU' => (string) $ultNSU,
            'maxNSU' => (string) $maxNSU,
            'last_run' => date('c'),
            'last_status' => $lastStatus
        ];
        file_put_contents("{$savePath}/metadata.json", json_encode($metadata, JSON_PRETTY_PRINT));
    }
}
