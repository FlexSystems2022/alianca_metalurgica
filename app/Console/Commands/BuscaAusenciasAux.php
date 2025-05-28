<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaAusenciasAux extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaAusenciasAux';

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

    private function getUltimaDataAusencia()
    {
        $dataRetro = date("Y-m-d", strtotime("-180 days"));
        //$dataRetro = date("Y-m-d", strtotime("2022-01-01"));
        $data = DateTime::createFromFormat('Y-m-d', $dataRetro);
        //var_dump($data);exit;
        return $data;
    }

    private function buscaAusencias()
    {
        $data = $this->getUltimaDataAusencia();
       
        //var_dump($data);exit;
        do {    
            $start = DateTime::createFromFormat('dmYHis', $data->format('dmYHis'));
            $finish = DateTime::createFromFormat('dmYHis', $start->format('dmYHis'));
            //$finish->modify('+1 day');

            $start_str = $start->format('dmY000000');
            $finish_str = $finish->format('dmY235959');
           
            $this->info("Buscando ausência...{$start_str} - {$finish_str}");
            $this->getAusenciaRecursivo(0, $start_str, $finish_str);
            $start->format('Y-m-d H:i:s');
            $start->modify("+1 day");
            $data = $start;
        } while (strtotime($start->format('Y-m-d')) <= strtotime(date('Y-m-d')));
    }


    private function getAusencia($id)
    {
        $sql = "SELECT * FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS WHERE ID = {$id}";
        return DB::connection('sqlsrv')->select($sql);
    }


    private function getAusenciaRecursivo($page = 0, $start, $finish)
    {
        $response = $this->restClient->get("absences/lastupdate/start/{$start}/finish/{$finish}", [
            'page' => $page,
            'size' => 100000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar ausências: {$errors}");
        } else {
            $responseData = $this->restClient->getResponseData();
            if ($responseData) {
                $content = $responseData['content'];
                if ($page == 0) {
                    $this->info("Armazendo {$responseData['totalElements']} registros...");
                }

                $this->info("API Nexti retornou os resultados: Pagina {$page}.");
                if (sizeof($content) > 0) {
                    $count = 0;
                    foreach ($content as $reg) {
                        //dd($reg);
                        var_dump($count++);
                        $this->addAusencia($reg);
                        //usleep(10000);
                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                     sleep(config('base-config.delay'));
                    $this->getAusenciaRecursivo($page + 1, $start, $finish);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function addAusencia($ausencia){
        $ausenciaExistente = $this->getAusencia($ausencia['id']);


        if(!empty($ausenciaExistente) && $ausenciaExistente[0]->REMOVED == 1 && $ausenciaExistente[0]->SITUACAO == 1){
            
        }else{
            $removed = isset($ausencia['removed']) && $ausencia['removed'] == true ? 1 : 0;
            $ausencia['lastUpdate'] = DateTime::createFromFormat('dmYHis', $ausencia['lastUpdate'])->format('Y-m-d H:i:s');
            $ausencia['startDateTime'] = DateTime::createFromFormat('dmYHis', $ausencia['startDateTime'])->format('Y-m-d H:i:s');
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

            $ausencia['lastUpdate'] = isset($ausencia['lastUpdate']) ? $ausencia['lastUpdate'] : '';
            $ausencia['personExternalId'] = isset($ausencia['personExternalId']) ? $ausencia['personExternalId'] : '';
            $ausencia['personId'] = isset($ausencia['personId']) ? $ausencia['personId'] : '';
            $ausencia['absenceSituationExternalId'] = isset($ausencia['absenceSituationExternalId']) ? $ausencia['absenceSituationExternalId'] : '';
            $ausencia['absenceSituationId'] = isset($ausencia['absenceSituationId']) ? $ausencia['absenceSituationId'] : '';
            $ausencia['finishDateTime'] = isset($ausencia['finishDateTime']) ? $ausencia['finishDateTime'] : '';
            $ausencia['startDateTime'] = isset($ausencia['startDateTime']) ? $ausencia['startDateTime'] : '';
            $ausencia['finishDateTime'] = isset($ausencia['finishDateTime']) ? $ausencia['finishDateTime'] : '';
            $ausencia['userRegisterId'] = isset($ausencia['userRegisterId']) ? $ausencia['userRegisterId'] : '';
            $ausencia['startMinute'] = isset($ausencia['startMinute']) ? $ausencia['startMinute'] : 0;
            $ausencia['finishMinute'] = isset($ausencia['finishMinute']) ? $ausencia['finishMinute'] : 0;
            $ausencia['note'] = isset($ausencia['note']) ? $ausencia['note'] : '';
            $ausencia['cidCode'] = isset($ausencia['cidCode']) ? $ausencia['cidCode'] : '';
            $ausencia['cidDescription'] = isset($ausencia['cidDescription']) ? $ausencia['cidDescription'] : '';
            $ausencia['cidId'] = isset($ausencia['cidId']) ? $ausencia['cidId'] : 0;
            $ausencia['medicalDoctorCrm'] = isset($ausencia['medicalDoctorCrm']) ? $ausencia['medicalDoctorCrm'] : '';
            $ausencia['medicalDoctorId'] = isset($ausencia['medicalDoctorId']) ? $ausencia['medicalDoctorId'] : 0;
            $ausencia['medicalDoctorName'] = isset($ausencia['medicalDoctorName']) ? $ausencia['medicalDoctorName'] : '';
            $ausencia['medicalDoctorName'] = str_replace("'","",$ausencia['medicalDoctorName']);
            $ausencia['medicalDoctorCrm'] = str_replace("'","",$ausencia['medicalDoctorCrm']);
            $ausencia['cidDescription'] = str_replace("'","",$ausencia['cidDescription']);
            $ausencia['note'] = str_replace("'","",$ausencia['note']);

            if(isset($ausenciaExistente[0])){
                $sql =  
                "
                    UPDATE ".env('DB_OWNER')."FLEX_RET_AUSENCIAS 
                    SET 
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.LASTUPDATE = CONVERT(DATETIME, '{$ausencia['lastUpdate']}', 120),
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME = CONVERT(DATETIME, '{$ausencia['finishDateTime']}', 120),
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.REMOVED = '{$removed}',
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.NOTE = '{$ausencia['note']}',
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.CIDCODE = '{$ausencia['cidCode']}',
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.CIDDESCRIPTION = '{$ausencia['cidDescription']}',
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.CIDID = {$ausencia['cidId']},
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.MEDICALDOCTORCRM = '{$ausencia['medicalDoctorCrm']}',
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.MEDICALDOCTORID = {$ausencia['medicalDoctorId']},
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.MEDICALDOCTORNAME = '{$ausencia['medicalDoctorName']}',
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.USERREGISTERID = '{$ausencia['userRegisterId']}',

                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = '{$tipo}',
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.SITUACAO = 0,
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.OBSERVACAO = '',
                    ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.INTEGRA_PROTHEUS = 0
                    WHERE ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID = {$ausencia['id']}
                "; 

                DB::connection('sqlsrv')->statement($sql);
                $this->info("Ausência {$ausencia['id']} Atualizada com Sucesso!");
            }else{
                $sql =
                "
                    INSERT INTO ".env('DB_OWNER')."FLEX_RET_AUSENCIAS (
                        ID, 
                        LASTUPDATE, 
                        PERSONEXTERNALID, 
                        PERSONID, 
                        NOTE, 
                        ABSENCESITUATIONEXTERNALID, 
                        ABSENCESITUATIONID, 
                        FINISHDATETIME, 
                        STARTDATETIME, 
                        STARTMINUTE, 
                        FINISHMINUTE, 
                        REMOVED, 
                        USERREGISTERID, 
                        INTEGRA_PROTHEUS, 
                        OBSERVACAO, 
                        CIDCODE, 
                        CIDDESCRIPTION, 
                        CIDID, 
                        MEDICALDOCTORCRM, 
                        MEDICALDOCTORID, 
                        MEDICALDOCTORNAME, 
                        TIPO, 
                        SITUACAO
                    )
                    VALUES (
                        {$ausencia['id']}, 
                        CONVERT(DATETIME, '{$ausencia['lastUpdate']}', 120), 
                        '{$ausencia['personExternalId']}', 
                        {$ausencia['personId']}, 
                        '{$ausencia['note']}', 
                        '{$ausencia['absenceSituationExternalId']}', 
                        {$ausencia['absenceSituationId']}, 
                        CONVERT(DATETIME, '{$ausencia['finishDateTime']}', 120), 
                        CONVERT(DATETIME, '{$ausencia['startDateTime']}', 120),
                        {$ausencia['startMinute']}, 
                        {$ausencia['finishMinute']}, 
                        '{$removed}', 
                        {$ausencia['userRegisterId']} , 
                        0, 
                        '', 
                        '{$ausencia['cidCode']}', 
                        '{$ausencia['cidDescription']}', 
                        {$ausencia['cidId']}, 
                        '{$ausencia['medicalDoctorCrm']}', 
                        {$ausencia['medicalDoctorId']}, 
                        '{$ausencia['medicalDoctorName']}', 
                        {$tipo}, 
                        0
                    )
                ";

                DB::connection('sqlsrv')->statement($sql);
                $this->info("AUSENCIA {$ausencia['id']} FOI INSERIDA COM SUCESSO.");
            }
        }
    }

    //-------------------------------------------------------------------------------------------------
    private function buscaColaboradoresSemMov(){
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."FLEX_COLABORADOR
            WHERE EXISTS(
                SELECT 1 FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.NUMEMP
                AND ".env('DB_OWNER')."FLEX_COLABORADOR.TIPCOL = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPCOL
                AND ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.NUMCAD
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
            )
            AND NOT EXISTS (
                SELECT 1  FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
                WHERE ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO
                AND EXISTS(
                    SELECT 1 FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                    WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.NUMEMP
                    AND ".env('DB_OWNER')."FLEX_COLABORADOR.TIPCOL = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPCOL
                    AND ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.NUMCAD
                    AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                    AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                    AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 0
                )
            )
            AND ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO = 1
            AND ".env('DB_OWNER')."FLEX_COLABORADOR.ID > 0
        ";

        return DB::connection('sqlsrv')->select($query);
    }
    
    private function buscaAusencias2()
    {
        $colaboradores = $this->buscaColaboradoresSemMov();

        $data = DateTime::createFromFormat('Y-m-d H:i:s', date('2018-01-01 00:00:00'));

        $count = 1;
        foreach($colaboradores as $colaborador){
            $this->info("Buscando ausência {$colaborador->NOMFUN}...");
            $this->getAusenciaRecursivo2(0, 0, 0, $colaborador);
            var_dump($count);
            $count++;
            usleep(50000);
        }
    }
    private function getAusenciaRecursivo2($page = 0, $start, $finish, $colaborador)
    {
        $response = $this->restClient->get("absences/personExternal/{$colaborador->IDEXTERNO}/start/01011900000000", [
            'page' => $page,
            'size' => 100000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar ausências: {$errors}");
        } else {
            $responseData = $this->restClient->getResponseData();
            if ($responseData) {
                $content = $responseData['content'];
                if ($page == 0) {
                    $this->info("Armazendo {$responseData['totalElements']} registros...");
                }

                $this->info("API Nexti retornou os resultados: Pagina {$page}.");
                if (sizeof($content) > 0) {
                    $count = 0;
                    //dd($content);
                    foreach ($content as $reg) {
                        var_dump($count++);
                        $this->addAusencia($reg);
                        //usleep(120000);
                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->getAusenciaRecursivo2($page + 1, $start, $finish, $colaborador);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
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
        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->buscaAusencias();
        $this->buscaAusencias2();
    }
}