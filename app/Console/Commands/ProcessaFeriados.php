<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class ProcessaFeriados extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaFeriados';

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
        "   SELECT
            ".env('DB_OWNER')."NEXTI_FERIADO.*,
            ".env('DB_OWNER')."NEXTI_FERIADO.DATFER AS holidayDate,
            ".env('DB_OWNER')."NEXTI_FERIADO.DESFER AS name,
            ".env('DB_OWNER')."NEXTI_POSTO.ID AS workplaceId
            FROM ".env('DB_OWNER')."NEXTI_FERIADO
            JOIN ".env('DB_OWNER')."NEXTI_POSTO
                ON ".env('DB_OWNER')."NEXTI_POSTO.EXTERNALID = ".env('DB_OWNER')."NEXTI_FERIADO.IDEXTERNO_POSTO";

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

    private function getFeriadosNovos()
    {   
        $where = 
        "  
            WHERE ".env('DB_OWNER')."NEXTI_FERIADO.TIPO = 0
            AND ".env('DB_OWNER')."NEXTI_POSTO.ID > 0
            AND ".env('DB_OWNER')."NEXTI_FERIADO.SITUACAO IN (0,2)
        ";
        $feriados = DB::connection('sqlsrv')->select($this->query . $where);
        return $feriados;
    }

    private function buscarFeriadosNovos() {
        
        $feriados = $this->getFeriadosNovos();
        $nFeriados = sizeof($feriados);
        $this->info("Iniciando envio de dados ({$nFeriados} feriados)");

        foreach ($feriados as $itemFeriado) {
            $array = [
                'holidayDate' => date("dmY", strtotime($itemFeriado->DATFER)),
                'holidayTypeId' => 3,
                'name' => $itemFeriado->DESFER,
                'workplaceId' => intval($itemFeriado->workplaceId),
            ];

            //var_dump($array);exit;

            $ret = $this->enviaFeriadoNovo($array, $itemFeriado->DATFER, $itemFeriado);
            sleep(1);
        }

        $this->info("Total de {$nFeriados} feriado(s) cadastrados!");
    }

    private function enviaFeriadoNovo($feriado, $datFeriado, $itemFeriado) {

        $this->info("Enviando o feriado {$feriado['name']}");

        $response = $this->restClient->post("holidays", [], [
            'json' => $feriado
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar a feriado {$feriado['name']} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($datFeriado, $itemFeriado, 2, $errors);

        } else {
            $this->info("Feriado {$feriado['name']} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $itemInserido = $responseData['value'];
            $id = $itemInserido['id'];

            $this->atualizaRegistro($datFeriado, $itemFeriado, 1, $id);
        }
    }

    private function getFeriadosExcluir()
    {   
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."NEXTI_FERIADO 
            WHERE ".env('DB_OWNER')."NEXTI_FERIADO.TIPO = 3 
            AND ".env('DB_OWNER')."NEXTI_FERIADO.SITUACAO IN(0,2)
            ORDER BY ".env('DB_OWNER')."NEXTI_FERIADO.DATFER DESC
        ";

        $feriados = DB::connection('sqlsrv')->select($query);

        return $feriados;
    }

    private function enviaFeriadosExcluir() {
        
        $feriados = $this->getFeriadosExcluir();
        $nFeriados = sizeof($feriados);
        $this->info("Iniciando exclusão de ({$nFeriados} feriados)");

        foreach ($feriados as $feriado) {
            $ret = $this->enviaFeriadoExcluir($feriado);
        }

        $this->info("Total de {$nFeriados} feriados(s) cadastrados!");
    }

    private function enviaFeriadoExcluir($feriado) {
        $this->info("Excluindo Feriado {$feriado->ID}");

        $feriado->ID = $feriado->ID == 0 ? 1: $feriado->ID;

        $response = $this->restClient->delete("holIDays/{$feriado->ID}", [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao excluir a feriado {$feriado->ID} para API Nexti: {$errors}");
            $this->excluirFeriado($feriado->ID);

        } else {
            $this->info("Exclusão {$feriado->ID} enviado com sucesso para API Nexti");
            $this->excluirFeriado($feriado->ID);
        }
    }

    private function excluirFeriado($id){
        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."NEXTI_FERIADO 
            WHERE ID = {$id}
        ";

        //var_dump($query);

        DB::connection('sqlsrv')->statement($query);
        
    }

    private function atualizaRegistro($datFeriado, $itemFeriado, $situacao, $id) {
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado.';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_FERIADO 
            SET SITUACAO = {$situacao}, 
            OBSERVACAO = '{$obs}', 
            ID = '{$id}' 
            WHERE DATFER = '{$itemFeriado->DATFER}'
            AND TABFED = {$itemFeriado->TABFED}
            AND IDEXTERNO_POSTO = '{$itemFeriado->IDEXTERNO_POSTO}'
        ");
        
    } 

    private function atualizaRegistroComErro($datFeriado, $itemFeriado, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_FERIADO 
            SET SITUACAO = {$situacao}, 
            OBSERVACAO = '{$obs}' 
            WHERE DATFER = '{$itemFeriado->DATFER}'
            AND TABFED = {$itemFeriado->TABFED}
            AND IDEXTERNO_POSTO = '{$itemFeriado->IDEXTERNO_POSTO}'
        ");
        
    } 

    private function getFeriadoRecursivo($page = 0)
    {
        $response = $this->restClient->get("holidays/all", [
            'page' => $page,
            'size' => 100000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar feriados: {$errors}");
        } else {
            $responseData = $this->restClient->getResponseData();
            //dd($responseData['totalPages'], sizeof($responseData['content']));
            if ($responseData) {
                $content = $responseData['content'];
                if ($page == 0) {
                    $this->info("Armazendo {$responseData['totalElements']} registros...");
                }

                $this->info("API Nexti retornou os resultados: Pagina {$page}.");
                //var_dump(sizeof($content));exit;
                if (sizeof($content) > 0) {
                    foreach ($content as $reg) {
                        $this->addFeriado($reg);
                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->getFeriadoRecursivo($page + 1);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function addFeriado($feriado)
    {   
        $feriado['holidayDate'] = DateTime::createFromFormat('dmY', $feriado['holidayDate'])->format('Y-m-d');
        $feriado['workplaceId'] = $feriado['workplaceId']??0;
        $sql =
            "INSERT INTO ".env('DB_OWNER')."NEXTI_RET_FERIADO
            (HOLIDAYDATE, HOLIDAYTYPEID, NAME, WORKPLACEID, ID)
            VALUES 
            ('{$feriado['holidayDate']}', {$feriado['holidayTypeId']}, '{$feriado['name']}', {$feriado['workplaceId']}, {$feriado['id']})";

        DB::connection('sqlsrv')->statement($sql);
        
        $this->info("Feriado foi inserido com sucesso.");    
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

        $this->enviaFeriadosExcluir();
        $this->buscarFeriadosNovos();
        //$this->getFeriadoRecursivo();
    }
}