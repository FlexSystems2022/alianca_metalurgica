<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaUnidade extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaUnidade';

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
        $this->query = "SELECT * FROM ".env('DB_OWNER')."FLEX_UNIDADE";
    }

    private function getUnidadesNovas()
    {
        $where = " WHERE ".env('DB_OWNER')."FLEX_UNIDADE.SITUACAO IN(0,2) AND ".env('DB_OWNER')."FLEX_UNIDADE.TIPO = 0";
        $unidades = DB::connection('sqlsrv')->select($this->query . $where);

        return $unidades;
    }

    private function getUnidadesAtualizar()
    {
        $where = " WHERE ".env('DB_OWNER')."FLEX_UNIDADE.SITUACAO IN(0,2) AND ".env('DB_OWNER')."FLEX_UNIDADE.TIPO = 1";
        $unidades = DB::connection('sqlsrv')->select($this->query . $where);

        return $unidades;
    }

    private function getUnidadesExcluir()
    {
        $where = " WHERE ".env('DB_OWNER')."FLEX_UNIDADE.SITUACAO IN(0,2) AND ".env('DB_OWNER')."FLEX_UNIDADE.TIPO = 3";
        $unidades = DB::connection('sqlsrv')->select($this->query . $where);

        return $unidades;
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

    private function enviaUnidadeNova($unidadeNexti, $unidade) {
        $name = $unidade->NOME;
        $this->info("Enviando o unidade {$name} para API Nexti");

        //var_dump(json_encode($unidade));
        $response = $this->restClient->post("businessunits", [], [
            'json' => $unidadeNexti
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o unidade {$name} para API Nexti: {$errors}");
            $this->atualizaRegistroComErro($unidade, 2, $errors);
        } else {
            $this->info("Unidade {$name} enviada com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $unidadeInserida = $responseData['value'];
            $id = $unidadeInserida['id'];

            $this->atualizaRegistro($unidade, 1, $id);
        }
    }

    private function enviaUnidadesNovas() {
        $this->info("Iniciando envio de dados:");
        $unidades = $this->getUnidadesNovas();
        $nUnidades = sizeof($unidades);
        
        foreach ($unidades as $unidade) {
            $unidadeNexti = [
                'active'        => true,
                'externalId'    => $unidade->IDEXTERNO,
                'name'          => $unidade->NOME,
                'companyNumber' => $unidade->CNPJ
            ];
            
            $ret = $this->enviaUnidadeNova($unidadeNexti, $unidade);
        }
        $this->info("Total de {$nUnidades} unidade(s) cadastradas!");
    }

    private function enviaUnidadesAtualizar()
    {
        $this->info("Iniciando atualização:");

        $unidades  = $this->getUnidadesAtualizar();
        $nUnidades = sizeof($unidades);

        foreach ($unidades as $unidade) {
            $unidadeNexti = [
                'active'        => true,
                'externalId'    => $unidade->IDEXTERNO,
                'name'          => $unidade->NOME,
                'companyNumber' => $unidade->CNPJ
            ];

            $ret = $this->enviaUnidadeAtualizar($unidade, $unidadeNexti);
        }

        $this->info("Total de {$nUnidades} unidade(s) atualizados!");
    }

    private function enviaUnidadeAtualizar($unidade, $unidadeNexti)
    {
        $id = $unidade->ID;
        $this->info("Atualizando o unidade {$id}");
        
        // var_dump(json_encode($unidade));exit;
        $endpoint = "businessunits/{$id}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $unidadeNexti
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o unidade {$id} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($unidade, 2, $errors);
        } else {
            $this->info("Unidade {$id} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $unidadeInserido = $responseData['value'];
            $id = $unidadeInserido['id'];

            $this->atualizaRegistro($unidade, 1, $id);
        }
    }

    private function atualizaRegistro($unidade, $situacao, $id) {
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_UNIDADE 
            SET ".env('DB_OWNER')."FLEX_UNIDADE.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_UNIDADE.OBSERVACAO = '', 
            ".env('DB_OWNER')."FLEX_UNIDADE.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_UNIDADE.IDEXTERNO = '{$unidade->IDEXTERNO}'
        ");
    } 

    private function atualizaRegistroComErro($unidade, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_UNIDADE 
            SET ".env('DB_OWNER')."FLEX_UNIDADE.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_UNIDADE.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_UNIDADE.IDEXTERNO = '{$unidade->IDEXTERNO}'
        ");
    } 

    private function enviaUnidadesExcluir()
    {
        $this->info("Iniciando exclusão:");

        $unidades  = $this->getUnidadesExcluir();
        $nUnidades = sizeof($unidades);

        foreach ($unidades as $unidade) {

            $ret = $this->enviaUnidadeExcluir($unidade, $unidade->EXTERNALID, $unidade->ID);
        }

        $this->info("Total de {$nUnidades} unidade(s) excluidos!");
    }

    private function enviaUnidadeExcluir($unidade, $externalId, $id)
    {
        $this->info("Excluindo o unidade {$externalId}");
        
        // var_dump(json_encode($unidade));exit;
        $endpoint = "businessunits/{$id}";
        $response = $this->restClient->delete($endpoint, [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao excluir o unidade {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);
        } else {
            $this->info("Unidade {$unidade} excluida com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            //var_dump($responseData);exit;

            $this->atualizaRegistro($unidade, 1, $id);
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

        $this->enviaUnidadesExcluir();
        $this->enviaUnidadesNovas();
        $this->enviaUnidadesAtualizar();
    }
}
