<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaSituacoes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaSituacoes';

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
            * 
            FROM ".env('DB_OWNER')."FLEX_SITUACAO
        ";
    }

    private function getResponseErrors() {
        $responseData = $this->restClient->getResponseData();
       
        $message = $responseData['message']??'';
        $comments = '';
        if(isset($responseData['comments']) && sizeof($responseData['comments']) > 0) {
            $comments = $responseData['comments'][0];
        }
        
        if($comments != '') 
            $message = $comments;

        return $message;
    }

    private function getResponseValue() {
        $responseData = $this->restClient->getResponseData();
        return $responseData;
    }

    private function atualizaRegistro($idexterno, $situacao, $id) {
        $obs = date('d/m/Y H:i:s') . ' - ' . 'Registrado cadastrado/atualizado com sucesso';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_SITUACAO 
            SET ".env('DB_OWNER')."FLEX_SITUACAO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_SITUACAO.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."FLEX_SITUACAO.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_SITUACAO.IDEXTERNO = '{$idexterno}'
        ");
    } 

    private function atualizaRegistroComErro($idexterno, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_SITUACAO 
            SET ".env('DB_OWNER')."FLEX_SITUACAO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_SITUACAO.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_SITUACAO.IDEXTERNO = '{$idexterno}'
        ");
    } 

    private function getSituacoesNovos()
    {   
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_SITUACAO.TIPO = 0 
            AND (".env('DB_OWNER')."FLEX_SITUACAO.SITUACAO = 0 
            OR ".env('DB_OWNER')."FLEX_SITUACAO.SITUACAO = 2)
        ";
        $situacoes = DB::connection('sqlsrv')->select($this->query . $where);

        return $situacoes;
    }

    private function getSituacoesAtualizar()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_SITUACAO.TIPO = 1 
            AND (".env('DB_OWNER')."FLEX_SITUACAO.SITUACAO = 0 
            OR ".env('DB_OWNER')."FLEX_SITUACAO.SITUACAO = 2)
        ";
        $situacoes = DB::connection('sqlsrv')->select($this->query . $where);

        return $situacoes;
    }

    private function enviaSituacaoNovo($situacao) {
        $name = $situacao['name'];
        $this->info("Enviando o situacao {$name} para API Nexti");

        $response = $this->restClient->post("/absencesituations", [], [
            'json' => $situacao
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o situacao {$name} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($situacao['externalId'], 2, $errors);

        } else {
            $this->info("Situacao {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $situacaoInserido = $responseData['value'];
            $id = $situacaoInserido['id'];

            $this->atualizaRegistro($situacao['externalId'], 1, $id);
        }
    }

    private function enviaSituacaoAtualizar($externalId, $situacao, $idSit) {
        $this->info("Atualizando o situacao {$externalId} via API Nexti");

        $endpoint = "/absencesituations/externalId/{$externalId}";
        $endpoint = "/absencesituations/{$idSit}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $situacao
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o situacao {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);

        } else {
            $this->info("Situacao {$externalId} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $situacaoInserido = $responseData['value'];
            $id = $situacaoInserido['id'];

            $this->atualizaRegistro($externalId, 1, $id);
        }
    }

    private function enviaSituacoesNovos() {
        $this->info("Iniciando envio de dados:");
        $situacoes = $this->getSituacoesNovos();
        $nSituacoes = sizeof($situacoes);

        foreach ($situacoes as $situacao) {
            $situacaoNexti = [
                'name' => $situacao->DESSIT,
                'absenceTypeId' => 1,
                'active' => true,
                'cid' => false,
                'externalId' => $situacao->IDEXTERNO,
                'medicalDoctor' => false,
                'removeAbsence' => true,
                'removeTemplate' => false,
                'requiredReplacement' => false,
                'showSituation' => true
            ];
            $ret = $this->enviaSituacaoNovo($situacaoNexti);
        }
        $this->info("Total de {$nSituacoes} situacao(s) cadastrados!");
    }

    private function enviaSituacoesAtualizar() {
        $this->info("Iniciando atualizações:");
        $situacoes = $this->getSituacoesAtualizar();
        $nSituacoes = sizeof($situacoes);
        
        foreach ($situacoes as $situacao) {
            $situacaoNexti = [
                'name' => $situacao->DESSIT,
                // 'absenceTypeId' => 1,
                // 'active' => true,
                // 'cid' => false,
                'externalId' => $situacao->IDEXTERNO,
                // 'medicalDoctor' => false,
                // 'removeAbsence' => true,
                // 'removeTemplate' => false,
                // 'requiredReplacement' => false,
                // 'showSituation' => true
            ];
            $ret = $this->enviaSituacaoAtualizar($situacao->IDEXTERNO, $situacaoNexti, $situacao->ID);
        }
        $this->info("Total de {$nSituacoes} situacao(s) atualizados!");
    }

    private function insertLogs($msg, $dataInicio, $dataFim) {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime,'{$dataInicio}',5), convert(datetime,'{$dataFim}',5), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
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

        $this->enviaSituacoesNovos();
        $this->enviaSituacoesAtualizar();
    }
}
