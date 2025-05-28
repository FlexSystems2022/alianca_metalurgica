<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helper\LogColaborador;

class ProcessaAusenciasCorrecao extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaAusenciasCorrecao';

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

    private function buscaAfastamentosReajustarDataFimOriginal(){
        $query = 
        "
            SELECT 
            FLEX_AUSENCIAS_PROTHEUS.*
            FROM FLEX_AUSENCIAS_PROTHEUS
            JOIN FLEX_COLABORADOR
                ON FLEX_COLABORADOR.IDEXTERNO = FLEX_AUSENCIAS_PROTHEUS.IDEXTERNO 
            WHERE FLEX_AUSENCIAS_PROTHEUS.SITUACAO = 1
            AND FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
            AND FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME = CONVERT(DATETIME, '1900-01-01', 120)
            AND FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('999', '99999', '01-001')
            AND FLEX_AUSENCIAS_PROTHEUS.TIPO IN(0,1)
            AND NOT EXISTS(
                SELECT * FROM FLEX_RET_AUSENCIAS
                WHERE FLEX_RET_AUSENCIAS.PERSONEXTERNALID = FLEX_AUSENCIAS_PROTHEUS.IDEXTERNO
                AND FLEX_RET_AUSENCIAS.STARTDATETIME >= FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME
                AND FLEX_RET_AUSENCIAS.FINISHDATETIME > FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME
                AND FLEX_RET_AUSENCIAS.FINISHDATETIME <> CONVERT(DATETIME, '1900-12-31', 120)
                AND FLEX_RET_AUSENCIAS.REMOVED = 0
                AND FLEX_RET_AUSENCIAS.ID <> FLEX_AUSENCIAS_PROTHEUS.ID
            )
            AND EXISTS(
                SELECT * FROM FLEX_RET_AUSENCIAS
                WHERE FLEX_RET_AUSENCIAS.PERSONEXTERNALID = FLEX_AUSENCIAS_PROTHEUS.IDEXTERNO
                AND FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID = FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID
                AND FLEX_RET_AUSENCIAS.STARTDATETIME = FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME
                AND FLEX_RET_AUSENCIAS.FINISHDATETIME <> FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME
                AND FLEX_RET_AUSENCIAS.FINISHDATETIME <> CONVERT(DATETIME, '1900-12-31', 120)
                AND FLEX_RET_AUSENCIAS.REMOVED = 0
                AND FLEX_RET_AUSENCIAS.ID = FLEX_AUSENCIAS_PROTHEUS.ID
            )

            UNION ALL 

            SELECT 
            FLEX_AUSENCIAS_PROTHEUS.*
            FROM FLEX_AUSENCIAS_PROTHEUS
            JOIN FLEX_COLABORADOR
                ON FLEX_COLABORADOR.IDEXTERNO = FLEX_AUSENCIAS_PROTHEUS.IDEXTERNO 
            WHERE FLEX_AUSENCIAS_PROTHEUS.SITUACAO = 1
            AND FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
            AND FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME > CONVERT(DATETIME, '1900-01-01', 120)
            AND FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('999', '99999', '01-001')
            AND FLEX_AUSENCIAS_PROTHEUS.TIPO IN(0,1)
            AND NOT EXISTS(
                SELECT * FROM FLEX_RET_AUSENCIAS
                WHERE FLEX_RET_AUSENCIAS.PERSONEXTERNALID = FLEX_AUSENCIAS_PROTHEUS.IDEXTERNO
                AND FLEX_RET_AUSENCIAS.STARTDATETIME = FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME
                AND FLEX_RET_AUSENCIAS.FINISHDATETIME <> FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME
                AND FLEX_RET_AUSENCIAS.REMOVED = 0
                AND FLEX_RET_AUSENCIAS.ID <> FLEX_AUSENCIAS_PROTHEUS.ID
            )
            AND EXISTS(
                SELECT * FROM FLEX_RET_AUSENCIAS
                WHERE FLEX_RET_AUSENCIAS.PERSONEXTERNALID = FLEX_AUSENCIAS_PROTHEUS.IDEXTERNO
                AND FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID = FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID
                AND FLEX_RET_AUSENCIAS.STARTDATETIME = FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME
                AND FLEX_RET_AUSENCIAS.FINISHDATETIME <> FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME
                AND FLEX_RET_AUSENCIAS.FINISHDATETIME <> CONVERT(DATETIME, '1900-12-31', 120)
                AND FLEX_RET_AUSENCIAS.REMOVED = 0
                AND FLEX_RET_AUSENCIAS.ID = FLEX_AUSENCIAS_PROTHEUS.ID
            )
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function reajustarDataFimOriginal(){
        $afastamentos = $this->buscaAfastamentosReajustarDataFimOriginal();

        if($afastamentos){
            foreach($afastamentos as $afastamento){

                $dataFim = date("dmY", strtotime($afastamento->FINISHDATETIME));

                $dataFim = $dataFim == '31121900' || $dataFim == '01011900' ? null : $dataFim;

                $array = [
                    "finishDateTime" => $dataFim . '000000',
                    'note' => 'Atualizado registro automaticamente pela integração'
                ];

                $response = $this->restClient->put("absences/" . $afastamento->ID, [], [
                    'json' => $array
                ])->getResponse();

                $responseData = $this->restClient->getResponseData();
                if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
                    $errors = $this->getResponseErrors();
                    $this->info("Problema na ausência {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID}: {$errors}");
                } else {
                    $this->info("Ausencia {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID} enviada com sucesso.");
                    $this->insereLog($afastamento, $afastamento->FINISHDATETIME);
                    //$this->deletaTabela($afastamento);
                }
            }
        }else{
            $this->info("Não Existem Afastamentos a Serem Corrigidos!");
        }
    }

    private function buscaAfastamentosCorrigirDataFim(){
        $query = 
        "
            SELECT 
            (
                SELECT TOP(1) ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME > ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME <= ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME
            ) AS INICIO_DEMISSAO,
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.* 
            FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME > ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME <= ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME
            )
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 0
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function corrigirAfastamentosDataFim(){
        $afastamentos = $this->buscaAfastamentosCorrigirDataFim();

        if($afastamentos){
            foreach($afastamentos as $afastamento){
                $dataAjustada = date("Y-m-d H:i:s", strtotime('-1 days', strtotime($afastamento->INICIO_DEMISSAO)));
                
                //$this->insereLog($afastamento, $dataAjustada);

                $array = [
                    "finishDateTime" => date("dmYHis", strtotime($dataAjustada)),
                    'note' => $afastamento->NOTE . ' - Atualizado registro automaticamente pela integração'
                ];

                $response = $this->restClient->put("absences/" . $afastamento->ID, [], [
                    'json' => $array
                ])->getResponse();

                $responseData = $this->restClient->getResponseData();
                if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
                    $errors = $this->getResponseErrors();
                    $this->info("Problema na ausência {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID}: {$errors}");
                } else {
                    $this->info("Ausencia {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID} enviada com sucesso.");
                    $this->insereLog($afastamento, $dataAjustada);
                    $this->deletaTabela($afastamento);
                }
            }
        }else{
            $this->info("Não Existem Afastamentos a Serem Corrigidos!");
        }
    }

    private function deletaTabela($afastamento){
        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            WHERE ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID =  {$afastamento->ID}
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function insereLog($afastamento, $dataAjustada){
        $hoje = date("Y-m-d H:i:s");

        $query = 
        "
            INSERT INTO LOG_ATT_AUSENCIA(ID_AUSENCIA, DATA_INICIO, DATA_FIM, DATA_FIM_AJUSTADA,DATA_AJUSTE)
            VALUES({$afastamento->ID}, CONVERT(DATETIME, '{$afastamento->STARTDATETIME}', 120), CONVERT(DATETIME, '{$afastamento->FINISHDATETIME}', 120), CONVERT(DATETIME, '{$dataAjustada}', 120), CONVERT(DATETIME, '{$hoje}', 120))
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function buscaAfastamentosRemover(){
        $query = 
        "
            SELECT 
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.* 
            FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '999'
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME < ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
            )
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 0

            UNION ALL 

            SELECT 
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.* 
            FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME <= ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME >= ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME
            )
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 0
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')

            UNION ALL 

            SELECT 
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.* 
            FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME <= ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME = CONVERT(DATETIME, '1900-01-01', 120)
            )
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 0
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')

            UNION ALL

            SELECT 
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.* 
            FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME <= ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                 AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME >= ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME <= ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME <= ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME
            )
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 0
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')

        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function removeAfastamentoSuperiorDemissao(){
        $afastamentos = $this->buscaAfastamentosRemover();

        if($afastamentos){
            foreach($afastamentos as $afastamento){
                $response = $this->restClient->delete("absences/" . $afastamento->ID, [], [])->getResponse();

                \App\Helper\LogColaborador::init()
                    ->empresa($afastamento->PERSONEXTERNALID)
                    ->matricula($afastamento->PERSONEXTERNALID)
                    ->processo('Exclusão de Ausência Correção removeAfastamentoSuperiorDemissao')
                    ->uri("absences/" . $afastamento->ID)
                    ->response([
                        'status' => $this->restClient->getResponse()->getStatusCode(),
                        'data' => $this->restClient->getResponseData()
                    ])
                    ->save();
        
                $responseData = $this->restClient->getResponseData();
                if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
                    $errors = $this->getResponseErrors();
                    $this->info("Problema na ausência {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID}: {$errors}");
                } else {
                    $this->info("Ausencia {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID} enviada com sucesso.");
                    $this->deletaTabela($afastamento);
                }
                
                sleep(1);
            }
        }else{
            $this->info("Não Existem Afastamentos a Serem Corrigidos!");
        }
    }

    private function buscaAfastamentosRemoverIgual(){
        $query = 
        "
            SELECT 
            (
                SELECT TOP(1) ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '999'
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME <= ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME
            ) AS INICIO_DEMISSAO,
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.* 
            FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '999'
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME <= ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME
            )
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 0

            UNION ALL 

            SELECT 
            (
                SELECT TOP(1) ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '999'
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME
            ) AS INICIO_DEMISSAO,
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.* 
            FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '999'
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME = CONVERT(DATETIME, '1900-12-31', 120)
            )
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 0
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function removeAfastamentoIgualDemissao(){
        $afastamentos = $this->buscaAfastamentosRemoverIgual();

        if($afastamentos){
            foreach($afastamentos as $afastamento){
                $response = $this->restClient->delete("absences/" . $afastamento->ID, [], [])->getResponse();

                \App\Helper\LogColaborador::init()
                    ->empresa($afastamento->PERSONEXTERNALID)
                    ->matricula($afastamento->PERSONEXTERNALID)
                    ->processo('Exclusão de Ausência Correção removeAfastamentoIgualDemissao')
                    ->uri("absences/" . $afastamento->ID)
                    ->response([
                        'status' => $this->restClient->getResponse()->getStatusCode(),
                        'data' => $this->restClient->getResponseData()
                    ])
                    ->save();
        
                $responseData = $this->restClient->getResponseData();
                if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
                    $errors = $this->getResponseErrors();
                    $this->info("Problema na ausência {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID}: {$errors}");
                } else {
                    $this->info("Ausencia {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID} enviada com sucesso.");
                    $this->deletaTabela($afastamento);
                }

                sleep(1);
            }
        }else{
            $this->info("Não Existem Afastamentos a Serem Corrigidos!");
        }
    }

    private function buscaAfastamentosCorrigirDataFimSemDataFim(){
        $query = 
        "
            SELECT 
            (
                SELECT TOP(1) ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '999'
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME > ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME = CONVERT(DATETIME, '1900-12-31', 120)
            ) AS INICIO_DEMISSAO,
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.* 
            FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '999'
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME > ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME = CONVERT(DATETIME, '1900-12-31', 120)
            )
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 0
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function corrigirAfastamentosDataFimSemDataFim(){
        $afastamentos = $this->buscaAfastamentosCorrigirDataFimSemDataFim();

        if($afastamentos){
            foreach($afastamentos as $afastamento){
                $dataAjustada = date("Y-m-d H:i:s", strtotime('-1 days', strtotime($afastamento->INICIO_DEMISSAO)));
                
                //$this->insereLog($afastamento, $dataAjustada);

                $array = [
                    "finishDateTime" => date("dmYHis", strtotime($dataAjustada))
                ];

                $response = $this->restClient->put("absences/" . $afastamento->ID, [], [
                    'json' => $array
                ])->getResponse();

                $responseData = $this->restClient->getResponseData();
                if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
                    $errors = $this->getResponseErrors();
                    $this->info("Problema na ausência {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID}: {$errors}");
                } else {
                    $this->info("Ausencia {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID} enviada com sucesso.");
                    $this->insereLog($afastamento, $dataAjustada);
                    $this->deletaTabela($afastamento);
                }
            }
        }else{
            $this->info("Não Existem Afastamentos a Serem Corrigidos!");
        }
    }

    private function buscaAfastamentosCorrigirDataFimSemDataFimGeral(){
        $query = 
        "
            SELECT 
                (
                    SELECT TOP(1) FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME
                    FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                    WHERE FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
                    AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                    AND NOT FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '999'
                    AND FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                    AND FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME > FLEX_RET_AUSENCIAS.STARTDATETIME
                    AND FLEX_RET_AUSENCIAS.FINISHDATETIME = CONVERT(DATETIME, '1900-12-31', 120)
                ) AS INICIO_AFA,
                FLEX_RET_AUSENCIAS.* 
            FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                WHERE FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0, 2)
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
                AND NOT FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '999'
                AND FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME > FLEX_RET_AUSENCIAS.STARTDATETIME
                AND FLEX_RET_AUSENCIAS.FINISHDATETIME = CONVERT(DATETIME, '1900-12-31', 120)
            )
            AND FLEX_RET_AUSENCIAS.TIPO = 0
            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function corrigirAfastamentosDataFimSemDataFimGeral()
    {
        $afastamentos = $this->buscaAfastamentosCorrigirDataFimSemDataFimGeral();

        if($afastamentos){
            foreach($afastamentos as $afastamento){
                $dataAjustada = date("Y-m-d H:i:s", strtotime('-1 days', strtotime($afastamento->INICIO_AFA)));
                
                $array = [
                    "finishDateTime" => date("dmYHis", strtotime($dataAjustada)),
                    'note' => $afastamento->NOTE . ' - Atualizado registro automaticamente pela integração'
                ];

                $response = $this->restClient->put("absences/" . $afastamento->ID, [], [
                    'json' => $array
                ])->getResponse();

                $responseData = $this->restClient->getResponseData();
                if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
                    $errors = $this->getResponseErrors();
                    $this->info("Problema na ausência {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID}: {$errors}");
                } else {
                    $this->info("Ausencia {$afastamento->ID} para colaborador {$afastamento->PERSONEXTERNALID} enviada com sucesso.");
                    $this->insereLog($afastamento, $dataAjustada);
                    $this->deletaTabela($afastamento);
                }
            }
        }else{
            $this->info("Não Existem Afastamentos a Serem Corrigidos!");
        }
    }

    private function reativaAusenciasRemovidasIndevidamenteFLEX(){
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
            SET 
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO = 0,
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.OBSERVACAO = '',
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ID = 0
            WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO = 1
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
            AND EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
                WHERE ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ID
                AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.REMOVED = 1
                AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.USERREGISTERID = 355717
            )
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
        $dataInicio = date('Y-m-d H:i:s A');
        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->reativaAusenciasRemovidasIndevidamenteFLEX();
        $this->reajustarDataFimOriginal();
        $this->corrigirAfastamentosDataFim();
        $this->corrigirAfastamentosDataFimSemDataFim();
        $this->removeAfastamentoSuperiorDemissao();
        $this->removeAfastamentoIgualDemissao();
        $this->corrigirAfastamentosDataFimSemDataFimGeral();
    }
}
