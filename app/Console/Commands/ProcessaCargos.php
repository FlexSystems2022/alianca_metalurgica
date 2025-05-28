<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaCargos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaCargos';

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
            FROM ".env('DB_OWNER')."FLEX_CARGO
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

    private function getCargosNovos()
    {   
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_CARGO.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_CARGO.SITUACAO IN(0,2)
        ";
        $cargos = DB::connection('sqlsrv')->select($this->query . $where);

        return $cargos;
    }

    private function getCargosAtualizar()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_CARGO.TIPO = 1 
            AND ".env('DB_OWNER')."FLEX_CARGO.SITUACAO IN(0,2)
        ";
        $cargos = DB::connection('sqlsrv')->select($this->query . $where);

        return $cargos;
    }

    private function enviaCargosNovos() {
        $this->info("Iniciando envio de dados:");
        $cargos = $this->getCargosNovos();
        $nCargos = sizeof($cargos);
        
        foreach ($cargos as $cargo) {
            $cargoNexti = [
                'name'          => $cargo->TITCAR,
                'externalId'    => $cargo->IDEXTERNO
            ];
            $ret = $this->enviaCargoNovo($cargoNexti);
        }
        $this->info("Total de {$nCargos} cargo(s) cadastrados!");
    }

    private function enviaCargoNovo($cargo) {
        $name = $cargo['name'];
        $this->info("Enviando o cargo {$name} para API Nexti");

        $response = $this->restClient->post("careers", [], [
            'json' => $cargo
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o cargo {$name} para API Nexti: {$errors}");
            $this->atualizaRegistroComErro($cargo['externalId'], 2, $errors);
        } else {
            $this->info("Cargo {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $cargoInserido = $responseData['value'];
            $id = $cargoInserido['id'];

            $this->atualizaRegistro($cargo['externalId'], 1, $id);
        }
    }

    private function enviaCargosAtualizar() {
        $this->info("Iniciando atualizações:");
        $cargos = $this->getCargosAtualizar();
        $nCargos = sizeof($cargos);
        
        foreach ($cargos as $cargo) {
            $cargoNexti = [
                'name' => $cargo->TITCAR,
                'externalId' => $cargo->IDEXTERNO
            ];
            $ret = $this->enviaCargoAtualizar($cargo->IDEXTERNO, $cargoNexti, $cargo->ID, $cargo);
        }
        $this->info("Total de {$nCargos} cargo(s) atualizados!");
    }

    private function enviaCargoAtualizar($externalId, $cargo, $idCargo, $dadosCargo) {
        $this->info("Atualizando o cargo {$externalId} via API Nexti");

        $endpoint = "careers/externalId/{$dadosCargo->IDEXTERNO}";
        //$endpoint = "careers/{$idCargo}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $cargo
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o cargo {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);

        } else {
            $this->info("Cargo {$externalId} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $cargoInserido = $responseData['value'];
            $id = $cargoInserido['id'];

            $this->atualizaRegistro($externalId, 1, $id);
        }
    }

    private function atualizaRegistro($idexterno, $situacao, $id) {
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_CARGO 
            SET ".env('DB_OWNER')."FLEX_CARGO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_CARGO.OBSERVACAO = '', 
            ".env('DB_OWNER')."FLEX_CARGO.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_CARGO.IDEXTERNO = '{$idexterno}'
        ");
    } 

    private function atualizaRegistroComErro($idexterno, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_CARGO 
            SET ".env('DB_OWNER')."FLEX_CARGO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_CARGO.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_CARGO.IDEXTERNO = '{$idexterno}'
        ");
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

        $this->enviaCargosNovos();
        $this->enviaCargosAtualizar();
    }
}
