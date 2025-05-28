<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaColaboradores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaColaboradores';

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

    private function getColaboradorRecursivo($page = 0)
    {
        $response = $this->restClient->get("persons/all", [
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
                    foreach ($content as $reg) {
                        $this->addColaborador($reg);
                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->getColaboradorRecursivo($page + 1);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function addColaborador($reg){
        $reg['cpf'] = $reg['cpf']??0;
        $reg['demissionDate'] = $reg['demissionDate']??'01011900000000';
        $reg['id'] = $reg['id']??0;
        $reg['externalId'] = isset($reg['externalId']) ? trim($reg['externalId']) : '0';
        $reg['externalCareerId'] = isset($reg['externalCareerId']) ? trim($reg['externalCareerId']) : '0';
        $reg['name'] = $reg['name']??0;
        $reg['enrolment'] = $reg['enrolment']??0;
        $reg['companyId'] = $reg['companyId']??0;
        $reg['personTypeId'] = $reg['personTypeId']??0;
        $reg['personSituationId'] = $reg['personSituationId']??0;
        $reg['cpf'] = trim($reg['cpf'])??0;
        $reg['pis'] = $reg['pis']??0;
        $reg['userAccountId'] = $reg['userAccountId']??0;
        $reg['email'] = $reg['email']??'';
        $reg['name'] = str_replace("'","",$reg['name']);
        $reg['enrolment'] = str_replace("'","",$reg['enrolment']);

        $reg['cpf'] = str_pad($reg['cpf'], 11, '0', STR_PAD_LEFT);

        $reg['demissionDate'] = DateTime::createFromFormat('dmYHis', $reg['demissionDate'])->format('Y-m-d H:i:s');

        $query = 
        "
            INSERT INTO ".env('DB_OWNER')."FLEX_COLABORADOR_AUX(ID, EXTERNALID, NAME, ENROLMENT, COMPANYID, PERSONTYPEID, PERSONSITUATIONID, CPF, PIS, EMAIL, USERACCOUNTID, EXTERNALCAREERID, DEMISSIONDATE)
            VALUES({$reg['id']}, '{$reg['externalId']}', '{$reg['name']}', '{$reg['enrolment']}', {$reg['companyId']}, {$reg['personTypeId']}, {$reg['personSituationId']}, '{$reg['cpf']}', '{$reg['pis']}', '{$reg['email']}', '{$reg['userAccountId']}', '{$reg['externalCareerId']}', CONVERT(DATETIME,'{$reg['demissionDate']}', 120))
        ";

        DB::connection('sqlsrv')->statement($query);

        $this->info("Colaborador {$reg['name']} Inserido com Sucesso!");
    }

    private function limpaTabela(){
        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."FLEX_COLABORADOR_AUX
        ";

        DB::connection('sqlsrv')->statement($query);
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
        //$this->AtualizaDiferenças();
        $this->limpaTabela();
        $this->getColaboradorRecursivo();
    }
}
