<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaTrocaEscalas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaTrocaEscalas';

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

    private function getUltimaDataTrocaEscalas()
    {
        $dataRetro = date("Y-m-d", strtotime("-1 days"));
        //$dataRetro = date("Y-m-d", strtotime("2020-08-01"));
        $data = DateTime::createFromFormat('Y-m-d', $dataRetro);
        //var_dump($data);exit;
        return $data;

    }

    private function getTrocaEscala($id)
    {
        $sql = "SELECT * FROM ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.ID = {$id}";
        return DB::connection('sqlsrv')->select($sql);
    }

    // private function buscaTrocaEscalas()
    // {
    //     // $start = $this->getUltimaDataTrocaEscalas();
    //     // $today = date('dmYHis');
    //     // $finish = date("dmYHis", strtotime("+1 day", $today));

    //     $data   = $this->getUltimaDataTrocaEscalas();
    //     // $start  = $data->format('dmYHis');
    //     // $finish = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'));
    //     // $finish = $finish->modify('+1 day');
    //     // $finish = $finish->format('dmYHis');

    //     $this->info("Buscando troca de escalas.../start/{$start_str}/finish/{$finish_str}");
    //     $this->getTrocaEscalaRecursivo(0, $start_str, $finish_str);
    // }

    private function buscaTrocaEscalas()
    {

        $data   = $this->getUltimaDataTrocaEscalas();
        
        // $start_str = date('dmY', strtotime('-1 day')) . '000001';
        // $finish_str = date('dmY') . '235959';
       
        // $this->info("Buscando troca de escalas.../start/{$start_str}/finish/{$finish_str}");
        // $this->getTrocaEscalaRecursivo(0, $start_str, $finish_str);

        do {    
            $start = DateTime::createFromFormat('dmYHis', $data->format('dmYHis'));
            $finish = DateTime::createFromFormat('dmYHis', $start->format('dmYHis'));
            $finish->modify('+1 day');

            $start_str = $start->format('dmY000000');
            $finish_str = $finish->format('dmY000000');
            // $start_str = date('dmY', strtotime('-1 day')) . '000001';
            // $finish_str = date('dmY') . '235959';
           
            $this->info("Buscando troca de escalas.../start/{$start_str}/finish/{$finish_str}");
            $this->getTrocaEscalaRecursivo(0, $start_str, $finish_str);


            $start->format('Y-m-d H:i:s');
            $start->modify("+1 day");
            $data = $start;
        } while (strtotime($start->format('Y-m-d')) <= strtotime(date('Y-m-d')));
    }

    private function getTrocaEscalaRecursivo($page, $start, $finish)
    {
        $response = $this->restClient->get("scheduletransfers/lastupdate/start/{$start}/finish/{$finish}", [
            'page' => $page,
            'size' => 10000
        ])->getResponse();

        $responseData = $this->restClient->getResponseData();
        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar troca de escalas: {$errors}");
        } else {
            $responseData = $this->restClient->getResponseData();
            //var_dump($responseData);exit;
            
            if ($responseData) {
                $content = $responseData['content'];
                if ($page == 0) {
                    $this->info("Armazendo {$responseData['totalElements']} registros...");
                }

                $this->info("API Nexti retornou os resultados: Pagina {$page}.");

                foreach ($content as $reg) {
                    $troca = $this->getTrocaEscala($reg['id']);
                    //print_r($troca);
                    $reg['removed'] = isset($reg['removed']) ? 1 : 0;

                    if (!isset($reg['personExternalId'])) {
                        $reg['personExternalId'] = 0;
                    }

                    $lastUpdate = $reg['lastUpdate'];
                    $lastUpdate = DateTime::createFromFormat('dmYHis', $lastUpdate)->format('Y-m-d H:i:s');
                    $reg['lastUpdate'] = $lastUpdate;

                    $transferDateTime = $reg['transferDateTime'];
                    $transferDateTime = DateTime::createFromFormat('dmYHis', $transferDateTime)->format('Y-m-d H:i:s');
                    $reg['transferDateTime'] = $transferDateTime;
                    //var_dump($reg);exit;
                    $this->addTrocaEscala($reg);
                
                    //usleep(120000);
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->getTrocaEscalaRecursivo($page + 1, $start, $finish);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function addTrocaEscala($troca)
    {
        $trocaExistente = $this->getTrocaEscala($troca['id']);
        
        // Se a troca já foi removida e processada, não faz nada
        if (!empty($trocaExistente) && $trocaExistente[0]->REMOVED == 1 && $trocaExistente[0]->SITUACAO == 1) {
            return;
        }

        $tipo = $troca['removed'] == 1 ? 3 : 0;
        $troca['lastUpdate'] = $this->formatDate($troca['lastUpdate']);
        $troca['scheduleExternalId'] = $troca['scheduleExternalId'] ?? '0';
        $obs = date("d/m/Y H:i:s") . ' - ' . ($trocaExistente ? 'Atualizado' : 'Cadastrado') . ' por BuscaTrocaEscalas(addTrocaEscala)';

        if ($trocaExistente) {
            $sql = "
                UPDATE ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS
                SET LASTUPDATE = CONVERT(DATETIME, '{$troca['lastUpdate']}', 120),
                    PERSONEXTERNALID = '{$troca['personExternalId']}',
                    PERSONID = {$troca['personId']},
                    TRANSFERDATETIME = CONVERT(DATETIME, '{$troca['transferDateTime']}', 120),
                    ROTATIONCODE = '{$troca['rotationCode']}',
                    SCHEDULEID = '{$troca['scheduleId']}',
                    ROTATIONID = '{$troca['rotationId']}',
                    REMOVED = '{$troca['removed']}',
                    USERREGISTERID = '{$troca['userRegisterId']}',
                    TIPO = {$tipo},
                    INTEGRA_PROTHEUS = 0,
                    SITUACAO = 0,
                    OBSERVACAO = '{$obs}',
                    SCHEDULEEXTERNALID = '{$troca['scheduleExternalId']}'
                WHERE ID = {$troca['id']}
            ";
            $this->info("Troca de escalas {$troca['id']} atualizada com sucesso.");
        } else {
            $sql = "
                INSERT INTO ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS
                (ID, LASTUPDATE, PERSONEXTERNALID, PERSONID, TRANSFERDATETIME, ROTATIONCODE,
                 SCHEDULEID, ROTATIONID, REMOVED, USERREGISTERID, TIPO, SITUACAO,
                 INTEGRA_PROTHEUS, SCHEDULEEXTERNALID, OBSERVACAO)
                VALUES
                ({$troca['id']}, CONVERT(DATETIME, '{$troca['lastUpdate']}', 120),
                 '{$troca['personExternalId']}', {$troca['personId']}, CONVERT(DATETIME, '{$troca['transferDateTime']}', 120),
                 {$troca['rotationCode']}, {$troca['scheduleId']}, {$troca['rotationId']},
                 '{$troca['removed']}', {$troca['userRegisterId']}, {$tipo}, 0, 0,
                 '{$troca['scheduleExternalId']}', '{$obs}')
            ";
            $this->info("Troca de escalas {$troca['id']} salva com sucesso.");
        }

        DB::connection('sqlsrv')->statement($sql);
    }

    private function formatDate($date)
    {
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        if ($dateTime) {
            return $dateTime->format('Y-m-d H:i:s');
        }
        return $date;
    }

    private function updateTrocaEscala($troca)
    {

        $troca['scheduleExternalId'] = $troca['scheduleExternalId'] ?? 0;
        //var_dump($troca);
        if ($troca['removed'] == 1) {
            $obs = date("d/m/Y") . ' - Atualizado por BuscaTrocaEscalas(updateTrocaEscala)';
            $sql =
                "UPDATE 
                ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS 
                SET LASTUPDATE = '{$troca['lastUpdate']}', 
                PERSONEXTERNALID = '{$troca['personExternalId']}',
                PERSONID = {$troca['personId']},
                TRANSFERDATETIME = '{$troca['transferDateTime']}',
                ROTATIONCODE = {$troca['rotationCode']},
                SCHEDULEEXTERNALID = '{$troca['scheduleExternalId']}',
                SCHEDULEID = {$troca['scheduleId']},
                ROTATIONID = {$troca['rotationId']},
                REMOVED = {$troca['removed']}, 
                USERREGISTERID = {$troca['userRegisterId']}, 
                TIPO = 3, 
                SITUACAO = 0,
                INTEGRA_PROTHEUS = 0,
                OBSERVACAO = '{$obs}'
                WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.ID = {$troca['id']}";
        } else {
            $obs = date("d/m/Y") . ' - Atualizado por BuscaTrocaEscalas(updateTrocaEscala)';
            $sql =
                "UPDATE 
                ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS 
                SET LASTUPDATE = '{$troca['lastUpdate']}', 
                PERSONEXTERNALID = '{$troca['personExternalId']}',
                PERSONID = {$troca['personId']},
                TRANSFERDATETIME = '{$troca['transferDateTime']}',
                ROTATIONCODE = {$troca['rotationCode']},
                SCHEDULEEXTERNALID = '{$troca['scheduleExternalId']}',
                SCHEDULEID = {$troca['scheduleId']},
                ROTATIONID = {$troca['rotationId']},
                REMOVED = {$troca['removed']}, 
                USERREGISTERID = {$troca['userRegisterId']},
                TIPO = 0, 
                SITUACAO = 0,
                INTEGRA_PROTHEUS = 0,
                OBSERVACAO = '{$obs}'
                WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.ID = {$troca['id']}";
        }

        DB::connection('sqlsrv')->statement($sql);
        
        //$this->info("Troca de escala {$troca['id']} foi atualizada com sucesso.");
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

        $this->buscaTrocaEscalas();
    }
}
