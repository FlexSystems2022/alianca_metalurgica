<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaSaneamento extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaSaneamento';

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

    private function buscaCargoRecursivo($page = 0)
    {
        $response = $this->restClient->get("careers/all", [
            'page' => $page,
            'size' => 100000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar Cargo: {$errors}");
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

                        $response = $this->restClient->delete("careers/" . $reg['id'], [], [])->getResponse();

                        if (!$this->restClient->isResponseStatusCode(200)) {
                            $errors = $this->getResponseErrors();
                            $this->info("Problema ao excluir o cargo {$reg['name']} na API Nexti: {$errors}");
                        } else {
                            $this->info("Cargo {$reg['name']} excluído com sucesso na API Nexti");
                        }

                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->buscaCargoRecursivo($page + 1);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function buscaClienteRecursivo($page = 0)
    {
        $response = $this->restClient->get("clients/all", [
            'page' => $page,
            'size' => 100000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar Cliente: {$errors}");
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

                        $response = $this->restClient->delete("clients/" . $reg['id'], [], [])->getResponse();

                        if (!$this->restClient->isResponseStatusCode(200)) {
                            $errors = $this->getResponseErrors();
                            $this->info("Problema ao excluir o Cliente {$reg['name']} na API Nexti: {$errors}");
                        } else {
                            $this->info("Cliente {$reg['name']} excluído com sucesso na API Nexti");
                        }

                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->buscaClienteRecursivo($page + 1);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function buscaPostoRecursivo($page = 0)
    {
        $response = $this->restClient->get("workplaces/all", [
            'page' => $page,
            'size' => 100000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar Posto: {$errors}");
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

                        $response = $this->restClient->delete("workplaces/" . $reg['id'], [], [])->getResponse();

                        if (!$this->restClient->isResponseStatusCode(200)) {
                            $errors = $this->getResponseErrors();
                            $this->info("Problema ao excluir o Posto {$reg['name']} na API Nexti: {$errors}");
                        } else {
                            $this->info("Posto {$reg['name']} excluído com sucesso na API Nexti");
                        }

                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->buscaPostoRecursivo($page + 1);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function atualizaPostoAntigo(){
        $query = 
        "   
            SELECT * FROM ".env('DB_OWNER')."NEXTI_RET_POSTO WHERE NAME NOT LIKE '%INATIVO%'
        "; 

        $postos = DB::connection('sqlsrv')->select($query);

        foreach($postos as $posto){
            $array = [
                "name" => 'INATIVO - ' . $posto->NAME
            ];

            $endpoint = "workplaces/{$posto->ID}/";

            $response = $this->restClient->put($endpoint, [], [
                'json' => $array
            ])->getResponse();
            if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
                $errors = $this->getResponseErrors();
                $this->info("Problema ao atualizar o posto {$posto->NAME} na API Nexti: {$errors}");
            } else {
                $this->info("Posto {$posto->NAME} atualizado com sucesso na API Nexti");
            }
        }
    }

    private function buscaSindicatoRecursivo($page = 0)
    {
        $response = $this->restClient->get("tradeunions/all", [
            'page' => $page,
            'size' => 100000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar Sindicato: {$errors}");
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

                        $response = $this->restClient->delete("tradeunions/" . $reg['id'], [], [])->getResponse();

                        if (!$this->restClient->isResponseStatusCode(200)) {
                            $errors = $this->getResponseErrors();
                            $this->info("Problema ao excluir o Sindicato {$reg['name']} na API Nexti: {$errors}");
                        } else {
                            $this->info("Sindicato {$reg['name']} excluído com sucesso na API Nexti");
                        }

                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->buscaSindicatoRecursivo($page + 1);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }


    private function removerTrocaSindicato(){
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."NEXTI_RET_TROCA_SINDICATOS
        ";

        $movimentos = DB::connection('sqlsrv')->select($query);

        foreach($movimentos as $mov){
            $endpoint = "tradeunionstransfers/{$mov->ID}";
            $response = $this->restClient->delete($endpoint, [], [])->getResponse();

            $responseData = $this->restClient->getResponseData();
            if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {

                $errors = $this->getResponseErrors();
                $this->info("Problema ao atualizar o troca de sindicato {$mov->ID} na API Nexti: {$errors}");

            } else {
                $this->info("troca de sindicato {$mov->ID} excluido com sucesso na API Nexti");
            }
        }
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

        //$this->buscaCargoRecursivo();
        //$this->buscaClienteRecursivo();
        //$this->buscaPostoRecursivo();
        //$this->atualizaPostoAntigo();
        $this->buscaSindicatoRecursivo();
        //$this->removerTrocaSindicato();
    }
}