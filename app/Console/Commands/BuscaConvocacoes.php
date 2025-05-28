<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaConvocacoes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaConvocacoes';

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
        $this->query = "";
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

    private function buscaConvocacaoRecursiva($page = 0)
    {
        $response = $this->restClient->get("notices/all", [
            'filter' => 'informe',
            'page' => $page,
            'size' => 100000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar colaboradores: {$errors}");
        } else {
            $responseData = $this->restClient->getResponseData();
            if ($responseData) {
                $content = $responseData['content'];
                if ($page == 0) {
                    $this->info("Armazendo {$responseData['totalElements']} registros...");
                }

                $this->info("API Nexti retornou os resultados: Pagina {$page}.");
                //var_dump(sizeof($content));exit;
                if (sizeof($content) > 0) {
                    $this->excluirConvocacoes();

                    foreach ($content as $reg) {

                        $this->addPosto($reg);
                        //usleep(15000);
                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->buscaConvocacaoRecursiva($page + 1);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function excluirConvocacoes(){
        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."FLEX_RET_CONVOCACOES
        ";

        return DB::connection('sqlsrv')->statement($query);
    }

    private function verificaExiste($reg){
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."FLEX_RET_CONVOCACOES
            WHERE ".env('DB_OWNER')."FLEX_RET_CONVOCACOES.ID = {$reg['id']}
        ";

        return DB::connection('sqlsrv')->statement($query);
    }

    private function addPosto($reg){
        $reg['id'] = $reg['id']??0;
        $reg['name'] = $reg['name']??0;
        $reg['noticeStatusId'] = $reg['noticeStatusId']??0;

        $query = 
        "
            INSERT INTO ".env('DB_OWNER')."FLEX_RET_CONVOCACOES(ID, NAME, NOTICESTATUSID)
            VALUES({$reg['id']}, '{$reg['name']}', {$reg['noticeStatusId']})
        ";

        DB::connection('sqlsrv')->statement($query);

        $this->info("Convocação {$reg['name']} Inserida com Sucesso!");
    }

    public function atualizaColaborador($reg){
        $reg['id'] = $reg['id']??0;
        $reg['noticeStatusId'] = $reg['noticeStatusId']??0;
        $reg['name'] = $reg['name']??0;

        var_dump($reg);exit;

        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_RET_CONVOCACOES
            SET 
            EXTERNALID = '{$reg['externalId']}', 
            NAME = '{$reg['name']}',
            NOTICESTATUSID = {$reg['noticeStatusId']},
            WHERE ID = {$reg['id']}
        ";

        DB::connection('sqlsrv')->statement($query);

        $this->info("Convocação {$reg['name']} Atualizada com Sucesso!");
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

        $this->buscaConvocacaoRecursiva();
        //$this->insereNovosColabProd();
    }
}