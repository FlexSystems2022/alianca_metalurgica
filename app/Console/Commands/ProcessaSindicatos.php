<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaSindicatos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaSindicatos';

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
        $this->query = "SELECT * FROM ".env('DB_OWNER')."FLEX_SINDICATOS ";
    }

    private function getSindicatosNovos()
    {   
        $where = " WHERE ".env('DB_OWNER')."FLEX_SINDICATOS.TIPO = 0 
        AND (".env('DB_OWNER')."FLEX_SINDICATOS.SITUACAO = 0 
        OR ".env('DB_OWNER')."FLEX_SINDICATOS.SITUACAO = 2)";
        $cargos = DB::connection('sqlsrv')->select($this->query . $where);
        return $cargos;
    }

     private function buscaSindicatosNovos() {
        $this->info("Iniciando envio de dados:");
        $sindicatos = $this->getSindicatosNovos();
        $nSindicatos = sizeof($sindicatos);

        foreach ($sindicatos as $sindicato) {
            $sindicatoNexti = [
                'active'        => true,
                'email'         => '',
                'externalId'    => $sindicato->IDEXTERNO,
                'name'          => $sindicato->NOMSIN,
                'registerDate'  => '31122010000000'
            ];

            $ret = $this->enviaSindicatoNovo($sindicatoNexti);
        }

        $this->info("Total de {$nSindicatos} cargo(s) cadastrados!");
    }

    private function enviaSindicatoNovo($sindicato) {
        $name = $sindicato['name'];
        $this->info("Enviando o sindicato {$name} para API Nexti");
        $response = $this->restClient->post("tradeunions", [], [
            'json' => $sindicato
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $responseData = $this->restClient->getResponseData();
            $this->info("Problema ao enviar o sindicato {$name} para API Nexti: {$errors}");
            $this->atualizaRegistroComErro($sindicato['externalId'], 2, $errors);
        } else {
            $this->info("Sindicato {$name} enviado com sucesso para API Nexti");
            $responseData = $this->restClient->getResponseData();
            $cargoInserido = $responseData['value']??0;
            $id = $cargoInserido['id'];

            $this->atualizaRegistro($sindicato['externalId'], 1, $id);
        }
    }

    private function atualizaRegistro($idexterno, $situacao, $id) {
        $obs = date("d/m/Y H:i:s") . ' REGISTRO CRIADO/ATUALIZADO COM SUCESSO';
        DB::connection('sqlsrv')->statement("UPDATE ".env('DB_OWNER')."FLEX_SINDICATOS SET SITUACAO = {$situacao}, OBSERVACAO = '{$obs}', ID = '{$id}' WHERE ".env('DB_OWNER')."FLEX_SINDICATOS.IDEXTERNO = '{$idexterno}'");
    } 

    private function atualizaRegistroComErro($idexterno, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("UPDATE ".env('DB_OWNER')."FLEX_SINDICATOS SET SITUACAO = {$situacao}, OBSERVACAO = '{$obs}' WHERE ".env('DB_OWNER')."FLEX_SINDICATOS.IDEXTERNO = '{$idexterno}'");
    }

    private function getResponseErrors() 
    {
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

    private function getResponseValue() 
    {
        $responseData = $this->restClient->getResponseData();
        return $responseData;
    }

    // ATUALIZAR
    private function getSindicatosAtualizar ()
    {
        $where = " WHERE ".env('DB_OWNER')."FLEX_SINDICATOS.TIPO = 1 
        AND (".env('DB_OWNER')."FLEX_SINDICATOS.SITUACAO = 0 
        OR ".env('DB_OWNER')."FLEX_SINDICATOS.SITUACAO = 2)";
        $cargos = DB::connection('sqlsrv')->select($this->query . $where);

        return $cargos;
    }

    private function buscarSindicatosAtualizar() {
        $this->info("Iniciando atualizações:");
        $sindicatos = $this->getSindicatosAtualizar();
        $nSindicatos = sizeof($sindicatos);
        
        foreach ($sindicatos as $sindicato) {
            $sindicatoNexti = [
                "active"        => true,
                "externalId"    => $sindicato->IDEXTERNO,
                'name'          => trim($sindicato->NOMSIN),
                'registerDate'  => '31122010000000',
            ];

            $ret = $this->enviaSindicatoAtualizar($sindicato->IDEXTERNO, $sindicatoNexti);
        }

        $this->info("Total de {$nSindicatos} sindicato(s) atualizados!");
    }

    private function enviaSindicatoAtualizar($idExterno, $sindicato) {
        $this->info("Atualizando o sindicato {$sindicato['externalId']} via API Nexti");

        $endpoint = "tradeunions/externalId/{$idExterno}";
        $response = $this->restClient->put($endpoint, [], ['json' => $sindicato])->getResponse();

        $responseData = $this->restClient->getResponseData();
        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o sindicato {$idExterno} na API Nexti: {$errors}");
            $this->atualizaRegistroComErro($idExterno, 2, $errors);

        } else {
            $id = $responseData['value']['id'];
            $this->info("Sindicato {$idExterno} atualizado com sucesso na API Nexti");
            $this->atualizaRegistro($idExterno, 1, $id);
        }
    }

    private function insertLogs($msg, $dataInicio, $dataFim) {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime,'{$dataInicio}',5), convert(datetime,'{$dataFim}',5), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }

    public function removerSindicato()
    {
        $endpoint = "tradeunions/all";
        $response = $this->restClient->get($endpoint, [
                'page' => 0,
                'size' => 1000
            ])->getResponse();
        $responseData = $this->restClient->getResponseData();
        $resultado = $responseData['content'];
        var_dump($resultado);exit;
        foreach ($resultado as $item) {
            $endpoint = "tradeunions/{$item['id']}";
            $response = $this->restClient->delete($endpoint, [])->getResponse();
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

        $this->buscaSindicatosNovos();
        $this->buscarSindicatosAtualizar();
    }
}
