<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class ProcessaContraChequeCompetencias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaContraChequeCompetencias';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $restClient = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');
        parent::__construct();
        $this->query = 
        "
            SELECT  
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.*,
            ".env('DB_OWNER')."FLEX_EMPRESA.ID AS COMPANYID
            FROM ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA
            JOIN ".env('DB_OWNER')."FLEX_EMPRESA
                ON ".env('DB_OWNER')."FLEX_EMPRESA.NUMEMP = ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.NUMEMP
                AND ".env('DB_OWNER')."FLEX_EMPRESA.CODFIL = ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.CODFIL
        ";
    }

   private function getResponseErrors()
    {
        $responseData = $this->restClient->getResponseData();

        $message = $responseData['message']??'';
        $comments = '';
        if (isset($responseData['comments']) && sizeof($responseData['comments']) > 0) {
            $comments = $responseData['comments'][0];
        }

        if ($comments != '') {
            $message = $comments;
        }

        return $message;
    }

    private function getResponseValue() {
        $responseData = $this->restClient->getResponseData();
        return $responseData;
    }

    private function atualizaRegistro($idexterno, $situacao, $id) {
        $obs = date('d/m/Y H:i:s') . ' - Registro enviado/atualizado na Nexti';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA 
            SET ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.IDEXTERNO = '{$idexterno}'");
    } 

    private function atualizaRegistroComErro($idexterno, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA 
            SET ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.IDEXTERNO = '{$idexterno}'");
    } 

    private function getCompetenciasNovas()
    {   
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.SITUACAO IN(0,2)
            ORDER BY ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.DATPAG
        ";
        $situacoes = DB::connection('sqlsrv')->select($this->query . $where);

        return $situacoes;
    }

    private function getCompetenciasAtualizar()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.TIPO = 1 
            AND ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.SITUACAO IN(0,2)
        ";
        $situacoes = DB::connection('sqlsrv')->select($this->query . $where);

        return $situacoes;
    }

    private function enviaCompetenciasNovas() {
        $this->info("Iniciando envio de dados:");
        $competencias = $this->getCompetenciasNovas();
        $nCompetencias = sizeof($competencias);

        foreach ($competencias as $competencia) {
            $periodoData = date('dmY', strtotime($competencia->PAYCHECKPERIODATE));

            $competenciaNexti = [
                'companyId' => $competencia->COMPANYID,
                'externalId' => $competencia->IDEXTERNO,
                'name' => $competencia->NAME,
                'paycheckPeriodDate' => $periodoData,
                //'exhibitionDate' => '01112024000000',
                'exhibitionDate' => date('dmY', strtotime($competencia->DATPAG)) . '000000'
            ];
            
            $ret = $this->enviaCompetenciaNovo($competenciaNexti);
        }
        $this->info("Total de {$nCompetencias} competência(s) cadastrados!");
    }

    private function enviaCompetenciaNovo($competencia) {
        $name = $competencia['name'];
        $this->info("Enviando a competência {$name} para API Nexti");

        //var_dump(json_encode($competencia));exit;
        // dd($competencia);
        $response = $this->restClient->post("/paycheckperiods", [], [
            'json' => $competencia
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar a competência {$name} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($competencia['externalId'], 2, $errors);

        } else {
            $this->info("Competência {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $situacaoInserido = $responseData['value'];
            $id = $situacaoInserido['id'];

            $this->atualizaRegistro($competencia['externalId'], 1, $id);
        }
    }

    private function enviaCompetenciaAtualizar($externalId, $competencia, $idCpt) {
        $this->info("Atualizando a competência {$externalId} via API Nexti");

        $endpoint = "/paycheckperiods/externalId/{$externalId}";
        //$endpoint = "/paycheckperiods/{$idCpt}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $competencia
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o competência {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);

        } else {
            $this->info("Competência {$externalId} atualizada com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $situacaoInserido = $responseData['value'];
            $id = $situacaoInserido['id'];

            $this->atualizaRegistro($externalId, 1, $id);
        }
    }

    private function enviaCompetenciasAtualizar() {
        $this->info("Iniciando atualizações:");
        $competencias = $this->getCompetenciasAtualizar();
        $nCompetencias = sizeof($competencias);
        
        foreach ($competencias as $competencia) {
            $periodoData = date('dmY', strtotime($competencia->PAYCHECKPERIODATE));

            $competenciaNexti = [
                //'companyId' => $competencia->COMPANYID,
                //'externalCompanyId' => $competencia->COMPANYEXTERNALID,
                //'externalId' => $competencia->IDEXTERNO,
                'name' => $competencia->NAME,
                //'paycheckPeriodDate' => $periodoData,
                'exhibitionDate' => date('dmY', strtotime('-1 days', strtotime($competencia->DATPAG))) . '000000'
            ];

            //var_dump($competenciaNexti);exit;
            $ret = $this->enviaCompetenciaAtualizar($competencia->IDEXTERNO, $competenciaNexti, $competencia->ID);
        }
        $this->info("Total de {$nCompetencias} competência(s) atualizados!");
    }

    private function buscarCompetenciasExcluir() {
        
        $competencias = $this->getCompetenciasExcluir();
        $nCompetencias = sizeof($competencias);
        $this->info("Iniciando exclusão de ({$nCompetencias} competencias)");

        foreach ($competencias as $itemCompetencia) {
            $excluir = ['externalId' => $itemCompetencia->IDEXTERNO];
            $ret = $this->enviaCompetenciaExcluir($excluir);
        }

        $this->info("Total de {$nCompetencias} empresas(s) cadastrados!");
    }

    private function enviaCompetenciaExcluir($competencia) {
        $this->info("Excluindo competencia {$competencia['externalId']}");

        $response = $this->restClient->delete("paycheckperiods/externalid/{$competencia['externalId']}", [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201) || !$this->restClient->isResponseStatusCode(200)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao excluir a competencia {$competencia['externalId']} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($competencia['externalId'], 2, $errors);

        } else {
            $this->info("Empresa {$nome} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $itemInserido = $responseData['value'];
            $id = $itemInserido['id'];

            $this->atualizaRegistro($competencia['externalId'], 1, $id);
        }
    }

    private function getCompetenciasExcluir()
    {   
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.TIPO = 3 
            AND ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.SITUACAO IN(0,2)
        ";
        $trocaPostos = DB::connection('sqlsrv')->select($this->query . $where);

        return $trocaPostos;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->buscarCompetenciasExcluir();
        $this->enviaCompetenciasNovas();
        $this->enviaCompetenciasAtualizar();
    }
}