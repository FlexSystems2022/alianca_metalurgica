<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaMotivosDemissao extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaMotivosDemissao';

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
            FROM ".env('DB_OWNER').".dbo.NEXTI_MOTIVO_DEMISSAO
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

    private function buscaMotivosNovos()
    {   
        $where = 
        " 
            WHERE ".env('DB_OWNER').".dbo.NEXTI_MOTIVO_DEMISSAO.TIPO = 0 
            AND ".env('DB_OWNER').".dbo.NEXTI_MOTIVO_DEMISSAO.SITUACAO IN(0,2)
        ";
        $empresas = DB::connection('sqlsrv')->select($this->query . $where);
        return $empresas;
    }

    private function buscaMotivosAtualizar()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER').".dbo.NEXTI_MOTIVO_DEMISSAO.TIPO = 1 
            AND ".env('DB_OWNER').".dbo.NEXTI_MOTIVO_DEMISSAO.SITUACAO IN (0,2)
        ";
        $empresas = DB::connection('sqlsrv')->select($this->query . $where);

        return $empresas;
    }

    private function buscarMotivosNovos() {
        
        $motivos = $this->buscaMotivosNovos();
        $nMotivos = sizeof($motivos);
        $this->info("Iniciando envio de dados ({$nMotivos} motivos)");

        foreach ($motivos as $motivo) {
            $motivoNexti = [
                'active' => $motivo->ACTIVE == 1 ? true : false,
                'name' => $motivo->EXTERNALID . ' - ' .$motivo->CODE . ' - ' . $motivo->NAME,
                'externalId' => $motivo->EXTERNALID
            ];
            //var_dump($motivoNexti);exit;
            $ret = $this->enviaMotivoNovo($motivoNexti, $motivo);
        }

        $this->info("Total de {$nMotivos} motivo(s) cadastrados!");
    }

    private function enviaMotivoNovo($motivoNexti, $motivo) {
        $nome = $motivo->NAME;
        $this->info("Enviando a motivo {$nome}");

        $response = $this->restClient->post("demissionreasons", [], [
            'json' => $motivoNexti
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o motivo {$nome} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($motivo->EXTERNALID, 2, $errors);

        } else {
            $this->info("Motivo {$nome} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $itemInserido = $responseData['value'];
            $id = $itemInserido['id'];

            $this->atualizaRegistro($motivo->EXTERNALID, 1, $id);
        }
    }

    private function enviaMotivosAtualizar() {
        $this->info("Iniciando atualizações:");
        $motivos = $this->buscaMotivosAtualizar();
        $nMotivos = sizeof($motivos);
        
        foreach ($motivos as $motivo) {
            $name = $motivo->NAME;
            if($motivo->ACTIVE == 0){
                $name = $motivo->EXTERNALID . ' - ' .$motivo->CODE . ' - ' . $motivo->NAME . ' - ' . $motivo->CPL_INATIVO;
            }

            $motivoNexti = [
                'active' => $motivo->ACTIVE == 1 ? true : false,
                'name' => $name,
                'externalId' => $motivo->EXTERNALID
            ];
            //var_dump($motivoNexti);exit;
            $ret = $this->enviaMotivoAtualizar($motivo->EXTERNALID, $motivoNexti);
        }
        $this->info("Total de {$nMotivos} motivo(s) atualizados!");
    }

    private function enviaMotivoAtualizar($externalId, $motivo) {
        $this->info("Atualizando o motivo {$externalId} via API Nexti");

        $endpoint = "demissionreasons/externalId/{$externalId}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $motivo
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o motivo {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);

        } else {
            $this->info("Motivo {$externalId} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $cargoInserido = $responseData['value'];
            $id = $cargoInserido['id'];

            $this->atualizaRegistro($externalId, 1, $id);
        }
    }

    private function atualizaRegistro($idExterno, $situacao, $id) {
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado.';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER').".dbo.NEXTI_MOTIVO_DEMISSAO 
            SET SITUACAO = {$situacao}, 
            OBSERVACAO = '{$obs}', 
            ID = '{$id}' 
            WHERE EXTERNALID = '{$idExterno}'
        ");
    } 

    private function atualizaRegistroComErro($idExterno, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER').".dbo.NEXTI_MOTIVO_DEMISSAO 
            SET SITUACAO = {$situacao}, 
            OBSERVACAO = '{$obs}' 
            WHERE EXTERNALID = '{$idExterno}'
        ");
    } 

    private function insertLogs($msg, $dataInicio, $dataFim) {
        $sql = 
        "
            INSERT INTO ".env('DB_OWNER').".dbo.NEXTI_LOG (DATA_INICIO, DATA_FIM, MSG) VALUES (convert(datetime,'{$dataInicio}',5), convert(datetime,'{$dataFim}',5), '{$msg}')
        ";
        
        DB::connection('sqlsrv')->statement($sql);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->enviaMotivosAtualizar();
        $this->buscarMotivosNovos();

        //$this->insertLogs("Empresas foram enviadas para o Nexti.", date('y-m-d H:i:s A'), date('y-m-d H:i:s A'));
    }
}
