<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaCoberturas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaCoberturas';

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
       
        $message = $responseData['message'];
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

    private function getUltimaDataAusencia()
    {
        // $sql = "SELECT MAX(LASTUPDATE) as LASTUPDATE FROM ".env('DB_OWNER').".dbo.NEXTI_RET_COBERTURAS";
        // $ultimoRegistro = DB::connection('sqlsrv')->select($sql);
        // $data = env("DataInicialBuscaTrocaPostos");
        
        // if (sizeof($ultimoRegistro) > 0) {
        //     if ($ultimoRegistro[0]->LASTUPDATE != null) {
        //         $data = $ultimoRegistro[0]->LASTUPDATE;
        //         $data = DateTime::createFromFormat('Y-m-d H:i:s', explode('.', $data)[0]);
        //         $data->modify("+1 second");
        //         //$data = $data->format('dmYHis');
        //         $data = DateTime::createFromFormat('Y-m-d H:i:s', "2022-04-19 00:00:00");
        //     } else {
        //         //$data = "2019-08-16 00:00:00";
        //         //$data = env('DataInicialBuscaAusencias');
        //         $data = DateTime::createFromFormat('Y-m-d H:i:s', env("DataInicialBuscaTrocaPostos"));
        //     }
        // }

        $data = DateTime::createFromFormat('Y-m-d H:i:s', "2023-05-01 00:00:00");

        return $data;
    }

    private function buscaAusencias()
    {
        $data = $this->getUltimaDataAusencia();
        
        // $start_str = date('dmY', strtotime('-1 day')) . '000001';
        // $finish_str = date('dmY') . '235959';

        // // $start_str = '1052021000001';
        // // $finish_str = '01052021235959';

        // $this->info("Buscando ausência...{$start_str} - {$finish_str}");
        // $this->getAusenciaRecursivo(0, $start_str, $finish_str);

        //var_dump($data);exit;
        do {    
            $start = DateTime::createFromFormat('dmYHis', $data->format('dmYHis'));
            $finish = DateTime::createFromFormat('dmYHis', $start->format('dmYHis'));
            $finish->modify('+1 day');

            $start_str = $start->format('dmYHis');
            //$today = date('dmYHis');
            $finish_str = $finish->format('dmYHis');
            // $start_str  = '21052021000001';
            // $finish_str = '01122099000000';
           
            $this->info("Buscando ausência...{$start_str} - {$finish_str}");
            $this->getAusenciaRecursivo(0, $start_str, $finish_str);
            $start->format('Y-m-d H:i:s');
            $start->modify("+1 day");
            $data = $start;
        } while (strtotime($start->format('Y-m-d')) <= strtotime(date('Y-m-d')));
    }


    private function getAusencia($id)
    {
        $sql = "SELECT * FROM ".env('DB_OWNER').".dbo.NEXTI_RET_COBERTURAS WHERE id = {$id}";
        return DB::connection('sqlsrv')->select($sql);
    }


    private function getAusenciaRecursivo($page = 0, $start, $finish)
    {
        $response = $this->restClient->get("replacements/lastupdate/start/{$start}/finish/{$finish}", [
            'page' => $page,
            'size' => 10000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar coberturas: {$errors}");
        } else {
            $responseData = $this->restClient->getResponseData();
            if ($responseData) {
                $content = $responseData['content'];
                if ($page == 0) {
                    $this->info("Armazendo {$responseData['totalElements']} registros...");
                }

                $this->info("API Nexti retornou os resultados: Pagina {$page}.");
                if (sizeof($content) > 0) {
                    foreach ($content as $reg) {
                        $this->addAusencia($reg);
                        usleep(10000);
                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->getAusenciaRecursivo($page + 1, $start, $finish);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function addAusencia($ausencia)
    {   
        //var_dump($ausencia);exit;
        $ausenciaExistente = $this->getAusencia($ausencia['id']);

        if ($ausenciaExistente) {
            $ausencia['lastUpdate'] = DateTime::createFromFormat('dmYHis', $ausencia['lastUpdate'])->format('Y-m-d H:i:s');

            $this->info("Ausência {$ausencia['id']} atualizada com sucesso!");
            //if (isset($ausencia['personExternalId']) && isset($ausencia['finishDateTime'])) {
            $removed = isset($ausencia['removed']) ? $ausencia['removed'] == false ? 0 : 1 : 0;
            $tipo = 0;
            if ($removed == 1) {
                $tipo = 3;
            }

            if (isset($ausencia['finishDateTime'])) {
                $ausencia['finishDateTime'] = DateTime::createFromFormat('dmYHis', $ausencia['finishDateTime'])->format('Y-m-d H:i:s');

                $ausencia['finishDateTime'] = explode(" ",  $ausencia['finishDateTime'])[0] . " 00:00:00";
            } else {
                $ausencia['finishDateTime'] = '1900-12-31 00:00:00';
            }

            $sql =  
            "
                UPDATE 
                ".env('DB_OWNER').".dbo.NEXTI_RET_COBERTURAS 
                SET ".env('DB_OWNER').".dbo.NEXTI_RET_COBERTURAS.lastUpdate = CONVERT(DATETIME, '{$ausencia['lastUpdate']}', 120),
                ".env('DB_OWNER').".dbo.NEXTI_RET_COBERTURAS.removed = '{$removed}',
                ".env('DB_OWNER').".dbo.NEXTI_RET_COBERTURAS.replacementReasonId ={$ausencia['replacementReasonId']}
                WHERE ".env('DB_OWNER').".dbo.NEXTI_RET_COBERTURAS.id = {$ausencia['id']}
            ";

            DB::connection('sqlsrv')->statement($sql);

            //var_dump($ausencia);
        } else {
            $ausencia['startDateTime'] = DateTime::createFromFormat('dmYHis', $ausencia['startDateTime'])->format('Y-m-d H:i:s');
            $ausencia['lastUpdate'] = DateTime::createFromFormat('dmYHis', $ausencia['lastUpdate'])->format('Y-m-d H:i:s');
            $ausencia['registerDate'] = DateTime::createFromFormat('dmYHis', $ausencia['registerDate'])->format('Y-m-d H:i:s');
            $removed = isset($ausencia['removed']) ? $ausencia['removed'] == false ? 0 : 1 : 0;
            $tipo = 0;
            if ($removed == 1) {
                $tipo = 3;
            }

            if (isset($ausencia['finishDateTime'])) {
                $ausencia['finishDateTime'] = DateTime::createFromFormat('dmYHis', $ausencia['finishDateTime'])->format('Y-m-d H:i:s');

                $ausencia['finishDateTime'] = explode(" ",  $ausencia['finishDateTime'])[0] . " 00:00:00";
            } else {
                $ausencia['finishDateTime'] = '1900-12-31 00:00:00';
            }

            $ausencia['replacementReasonName']  = isset($ausencia['replacementReasonName']) ? $this->limpaString($ausencia['replacementReasonName']) : '';

            //$ausencia['replacementReasonName']  = isset($ausencia['replacementReasonName']) ? $ausencia['replacementReasonName'] : '';

            $ausencia['replacementResourceExternalId'] = isset($ausencia['replacementResourceExternalId']) ? $ausencia['replacementResourceExternalId'] : '';
            $ausencia['replacementResourceName'] = isset($ausencia['replacementResourceName']) ? $ausencia['replacementResourceName'] : '';
            $ausencia['note']                 = isset($ausencia['note']) ? $ausencia['note'] : '';
            $ausencia['replacementReasonId']  = isset($ausencia['replacementReasonId']) ? $ausencia['replacementReasonId'] : 0;
            $ausencia['replacementTypeId']  = isset($ausencia['replacementTypeId']) ? $ausencia['replacementTypeId'] : 0;
            $ausencia['absenteeId']  = isset($ausencia['absenteeId']) ? $ausencia['absenteeId'] : 0;
            $ausencia['workplaceId']  = isset($ausencia['workplaceId']) ? $ausencia['workplaceId'] : 0;
            $ausencia['replacementResourceId']  = isset($ausencia['replacementResourceId']) ? $ausencia['replacementResourceId'] : 0;
            $ausencia['shiftId']  = isset($ausencia['shiftId']) ? $ausencia['shiftId'] : 0;
            $ausencia['replacementReasonExternalId']  = isset($ausencia['replacementReasonExternalId']) ? $ausencia['replacementReasonExternalId'] : '';
            $ausencia['absenteeExternalId']  = isset($ausencia['absenteeExternalId']) ? $ausencia['absenteeExternalId'] : '';
            $ausencia['shiftExternalId']  = isset($ausencia['shiftExternalId']) ? $ausencia['shiftExternalId'] : '';
            $ausencia['personExternalId']  = isset($ausencia['personExternalId']) ? $ausencia['personExternalId'] : '';
            $ausencia['workplaceExternalId']  = isset($ausencia['workplaceExternalId']) ? $ausencia['workplaceExternalId'] : '';

            $ausencia['note'] = str_replace("'", "",$ausencia['note']);
            $ausencia['workplaceExternalId'] = str_replace("'", "",$ausencia['workplaceExternalId']);

            $ausencia['note'] = substr($ausencia['note'], 0, 250);
            $ausencia['note'] = mb_convert_encoding($this->limpaString($ausencia['note']), 'UTF-16');
            // $ausencia['note'] = $this->limpaString($ausencia['note']);
            // $ausencia['note'] = preg_replace('/[0-9\@\.\;\""]+/', '', $ausencia['note']);
            // $ausencia['note'] = str_replace('-', '', $ausencia['note']);

            //$ausencia['finishDateTime'] = str_replace("0202", "2020",$ausencia['finishDateTime']);

            // $ausencia['replacementReasonId'] == 215 || $ausencia['replacementReasonId'] == 213 || $ausencia['replacementReasonId'] == 370 || $ausencia['replacementReasonId'] == 265 || $ausencia['replacementReasonId'] == 211

            $sql =
            "INSERT INTO ".env('DB_OWNER').".dbo.NEXTI_RET_COBERTURAS 
            (removed, replacementReasonName, replacementResourceExternalId, replacementTypeId, shiftExternalId, replacementResourceName, finishDateTime, personId, workplaceId, registerDate, replacementReasonId, note, absenteeId, id, replacementResourceId, personExternalId, replacementReasonExternalId, workplaceExternalId, shiftId, startDateTime, absenteeExternalId, lastUpdate, tipo, situacao)
            VALUES 
            (   
                {$removed}, 
                '{$ausencia['replacementReasonName']}',
                '{$ausencia['replacementResourceExternalId']}',
                {$ausencia['replacementTypeId']},
                '{$ausencia['shiftExternalId']}',
                '{$ausencia['replacementResourceName']}',
                CONVERT(DATETIME, '{$ausencia['finishDateTime']}', 120),
                {$ausencia['personId']},
                {$ausencia['workplaceId']},
                CONVERT(DATETIME, '{$ausencia['registerDate']}', 120),
                {$ausencia['replacementReasonId']},
                '{$ausencia['note']}',
                {$ausencia['absenteeId']},
                {$ausencia['id']},
                {$ausencia['replacementResourceId']},
                '{$ausencia['personExternalId']}',
                '{$ausencia['replacementReasonExternalId']}',
                '{$ausencia['workplaceExternalId']}',
                {$ausencia['shiftId']},
                CONVERT(DATETIME, '{$ausencia['startDateTime']}', 120),
                '{$ausencia['absenteeExternalId']}',
                CONVERT(DATETIME, '{$ausencia['lastUpdate']}', 120),
                0,
                0
            )";

            DB::connection('sqlsrv')->statement($sql);
            $this->info("Coberturas {$ausencia['id']} foi inserida com sucesso.");
        }
    }

    public function limpaString($string)
    {
        $retorno = strtr(utf8_decode($string),
            utf8_decode('ŠŒŽšœžŸ¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ'),
            'SOZsozYYuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy');
        return $retorno;
    }

    private function insertLogs($msg, $dataInicio, $dataFim)
    {
        $sql = "INSERT INTO ".env('DB_OWNER').".dbo.NEXTI_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime, '{$dataInicio}', 120), convert(datetime, '{$dataFim}', 120), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }
   

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dataInicio = date('Y-m-d H:i:s');
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $ret = $this->buscaAusencias();

        //$this->consultaAusencia();
        $dataFim = date('Y-m-d H:i:s');
    }
}