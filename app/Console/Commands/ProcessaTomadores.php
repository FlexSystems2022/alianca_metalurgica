<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaTomadores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTomadores';

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
            FROM ".env('DB_OWNER')."FLEX_CLIENTE
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

    private function getResponseValue()
    {
        $responseData = $this->restClient->getResponseData();
        return $responseData;
    }

    private function atualizaRegistro($idexterno, $situacao, $id)
    {
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_CLIENTE 
            SET ".env('DB_OWNER')."FLEX_CLIENTE.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_CLIENTE.OBSERVACAO = '', 
            ".env('DB_OWNER')."FLEX_CLIENTE.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_CLIENTE.IDEXTERNO = '{$idexterno}'
        ");
    }

    private function atualizaRegistroComErro($idexterno, $situacao, $obs = '')
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_CLIENTE 
            SET ".env('DB_OWNER')."FLEX_CLIENTE.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_CLIENTE.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_CLIENTE.IDEXTERNO = '{$idexterno}'
        ");
    }

    private function getTomadoresNovos()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_CLIENTE.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_CLIENTE.SITUACAO IN(0,2)
        ";
        $tomadores = DB::connection('sqlsrv')->select($this->query . $where);

        return $tomadores;
    }

    private function getTomadoresAtualizar()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_CLIENTE.TIPO = 1 
            AND ".env('DB_OWNER')."FLEX_CLIENTE.SITUACAO IN(0,2)
        ";
        $tomadores = DB::connection('sqlsrv')->select($this->query . $where);

        return $tomadores;
    }

    private function enviaTomadoresNovos()
    {
        $tomadores = $this->getTomadoresNovos();
        $nTomadores = sizeof($tomadores);

        $this->info("Iniciando envio de {$nTomadores} tomadores(s) para API Nexti");

        foreach ($tomadores as $tomador) {
            $tomadorNexti = [
                'name' => $tomador->NOMOEM,
                'externalId' => $tomador->IDEXTERNO,
                'active' => true
            ];
            $ret = $this->enviaTomadorNovo($tomadorNexti);
        }
    }

    private function enviaTomadorNovo($tomador)
    {
        $name = $tomador['name'];
        $this->info("Enviando o tomador {$name} para API Nexti");

        $response = $this->restClient->post("clients", [], [
            'json' => $tomador
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o tomador {$name} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($tomador['externalId'], 2, $errors);
        } else {
            $this->info("Tomador {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $tomadorInserido = $responseData['value'];
            $id = $tomadorInserido['id'];

            $this->atualizaRegistro($tomador['externalId'], 1, $id);
        }
    }

    private function enviaTomadoresAtualizar()
    {
        $tomadores = $this->getTomadoresAtualizar();
        $nTomadores = sizeof($tomadores);

        $this->info("Iniciando atualização de {$nTomadores} tomadores(s) para API Nexti");

        foreach ($tomadores as $tomador) {
            $tomadorNexti = [
                'name' => $tomador->NOMOEM,
                'externalId' => $tomador->IDEXTERNO,
                'active' => true
            ];
            
            $ret = $this->enviaTomadorAtualizar($tomador->IDEXTERNO, $tomadorNexti);
        }
    }

    private function enviaTomadorAtualizar($externalId, $tomador)
    {
        $this->info("Atualizando o tomador {$externalId} via API Nexti");

        $endpoint = "clients/externalId/{$externalId}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $tomador
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o tomador {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);
        } else {
            $this->info("Tomador {$externalId} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $tomadorInserido = $responseData['value'];
            $id = $tomadorInserido['id'];

            $this->atualizaRegistro($externalId, 1, $id);
        }
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

        $this->enviaTomadoresAtualizar();
        $this->enviaTomadoresNovos();
    }
}
