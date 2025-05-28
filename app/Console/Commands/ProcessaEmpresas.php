<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaEmpresas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaEmpresas';

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
            'true' as active,
            ".env('DB_OWNER')."FLEX_EMPRESA.RAZSOC as companyName,
            ".env('DB_OWNER')."FLEX_EMPRESA.IDEXTERNO as externalId,
            ".env('DB_OWNER')."FLEX_EMPRESA.NOMFIL as fantasyName,
            ".env('DB_OWNER')."FLEX_EMPRESA.CNPJ as companyNumber
            FROM ".env('DB_OWNER')."FLEX_EMPRESA
        ";

    }

    private function getResponseErrors() {
        $responseData = $this->restClient->getResponseData();
       
        $message = $responseData['message'];
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

    private function getEmpresasNovos()
    {   
        $where = " WHERE ".env('DB_OWNER')."FLEX_EMPRESA.TIPO = 0 AND (".env('DB_OWNER')."FLEX_EMPRESA.SITUACAO = 0 OR ".env('DB_OWNER')."FLEX_EMPRESA.SITUACAO = 2)";
        $trocaPostos = DB::connection('sqlsrv')->select($this->query . $where);
        return $trocaPostos;
    }

    private function getEmpresasExcluir()
    {   
        $where = " WHERE ".env('DB_OWNER')."FLEX_EMPRESA.TIPO = 3 AND (".env('DB_OWNER')."FLEX_EMPRESA.SITUACAO = 0 OR ".env('DB_OWNER')."FLEX_EMPRESA.SITUACAO = 0)";
        $trocaPostos = DB::connection('sqlsrv')->select($this->query . $where);

        return $trocaPostos;
    }

    private function getEmpresaAtualizar()
    {
        $where = " WHERE ".env('DB_OWNER')."FLEX_EMPRESA.TIPO = 1 AND (".env('DB_OWNER')."FLEX_EMPRESA.SITUACAO = 0 OR ".env('DB_OWNER')."FLEX_EMPRESA.SITUACAO = 2)";
        $colaboradores = DB::connection('sqlsrv')->select($this->query . $where);

        return $colaboradores;
    }

    private function buscarEmpresasNovos() {
        
        $empresas = $this->getEmpresasNovos();
        $nEmpresas = sizeof($empresas);
        $this->info("Iniciando envio de dados ({$nEmpresas} empresas)");

        foreach ($empresas as $itemEmpresa) {
            $ret = $this->enviaEmpresaNovo($itemEmpresa);
        }

        $this->info("Total de {$nEmpresas} empresas(s) cadastrados!");
    }

    private function enviaEmpresaNovo($empresa) {
        $nome = $empresa->companyName;
        $this->info("Enviando a empresa {$nome}");
        $empresa->companyNumber = str_pad($empresa->companyNumber, 14 ,'0', STR_PAD_LEFT);

        $response = $this->restClient->post("companies", [], [
            'json' => $empresa
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar a empresa {$nome} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($empresa->externalId, 2, $errors);

        } else {
            $this->info("Empresa {$nome} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $itemInserido = $responseData['value'];
            $id = $itemInserido['id'];

            $this->atualizaRegistro($empresa->externalId, 1, $id);
        }
    }


    private function buscarEmpresasExcluir() {
        
        $empresas = $this->getEmpresasExcluir();
        $nEmpresas = sizeof($empresas);
        $this->info("Iniciando exclusão de ({$nEmpresas} empresas)");

        foreach ($empresas as $itemEmpresa) {
            $excluir = ['id' => $itemEmpresa->id, 'externalId' => $itemEmpresa->externalId];
            $ret = $this->enviaEmpresaExcluir($excluir);
        }

        $this->info("Total de {$nEmpresas} empresas(s) cadastrados!");
    }

    private function enviaEmpresaExcluir($empresa) {
        $this->info("Excluindo empresa {$empresa['id']}");

        $response = $this->restClient->delete("companies/{$empresa['id']}", [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao excluir a empresa {$empresa['id']} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($empresa['externalId'], 2, $errors);

        } else {
            $this->info("Empresa {$nome} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $itemInserido = $responseData['value'];
            $id = $itemInserido['id'];

            $this->atualizaRegistro($empresa['externalId'], 1, $id);
        }
    }

    private function enviaEmpresasAtualizar()
    {
        $this->info("Iniciando atualização:");

        $empresas  = $this->getEmpresaAtualizar();
        $nEmpresas = sizeof($empresas);

        foreach ($empresas as $itemEmpresa) {
            $ret = $this->enviaEmpresaAtualizar($itemEmpresa->externalId, $itemEmpresa);
        }

        $this->info("Total de {$nEmpresas} empresa(s) atualizados!");
    }

    private function enviaEmpresaAtualizar($externalId, $empresa)
    {
        $this->info("Atualizando a empresa {$externalId}");

        //var_dump($empresa->active);exit;
        $empresa->active = true;
        $endpoint = "companies/externalId/{$externalId}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $empresa
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar a empresa {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);
        } else {
            $this->info("Empresa {$externalId} atualizada com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $empresaInserido = $responseData['value'];
            $id = $empresaInserido['id'];

            $this->atualizaRegistro($externalId, 1, $id);
        }
    }

    private function atualizaRegistro($idExterno, $situacao, $id) {
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado.';
        DB::connection('sqlsrv')->statement("UPDATE ".env('DB_OWNER')."FLEX_EMPRESA SET SITUACAO = {$situacao}, OBSERVACAO = '{$obs}', ID = '{$id}' WHERE IDEXTERNO = '{$idExterno}'");
    } 

    private function atualizaRegistroComErro($idExterno, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("UPDATE ".env('DB_OWNER')."FLEX_EMPRESA SET SITUACAO = {$situacao}, OBSERVACAO = '{$obs}' WHERE IDEXTERNO = '{$idExterno}'");
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
        $dataInicio = date('y-m-d H:i:s A');

        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        // $this->enviaEmpresaExcluir();
        $this->enviaEmpresasAtualizar();
        $this->buscarEmpresasNovos();
    }
}