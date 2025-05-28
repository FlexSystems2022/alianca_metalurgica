<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaAusenciasDemissao extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaAusenciasDemissao';

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
            ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.*,
            ".env('DB_OWNER')."NEXTI_MOTIVO_DEMISSAO.NAME,
            ".env('DB_OWNER')."NEXTI_MOTIVO_DEMISSAO.ID AS ID_MOTIVO_DEMISSAO
            FROM ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO
            LEFT JOIN ".env('DB_OWNER')."NEXTI_MOTIVO_DEMISSAO
                ON CAST(".env('DB_OWNER')."NEXTI_MOTIVO_DEMISSAO.CODE AS INT) = CAST(".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.MOTIVO AS INT)
                AND ".env('DB_OWNER')."NEXTI_MOTIVO_DEMISSAO.ACTIVE = 1
        ";
    }

    private function getAusenciaNovos()
    {
        $where =
        " 
            WHERE ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.TIPO = 0
            AND ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.SITUACAO IN (0,2)
            ORDER BY ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.NUMEMP, 
            ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.NUMCAD, 
            ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.STARTDATETIME
        ";
        $ausencias = DB::connection('sqlsrv')->select($this->query . $where);
        
        return $ausencias;
    }

    private function buscarAusenciasNovos()
    {
        $ausencias = $this->getAusenciaNovos();
        $nAusencias = sizeof($ausencias);
        $this->info("Iniciando envio de dados ({$nAusencias} ausências)");
        
        foreach ($ausencias as $itemAusencia) {
            $arrayAusencia = array(
                "absenceSituationId"            => 12352,
                "startDateTime"                 => date("dmY000000", strtotime($itemAusencia->STARTDATETIME)),
                "startMinute"                   => intval($itemAusencia->STARTMINUTE) == 0 ? null : $itemAusencia->STARTMINUTE,
                "personExternalId"              => $itemAusencia->PERSONEXTERNALID,
                "personId"                      => intval($itemAusencia->PERSONID),
                "finishDateTime"                => NULL,
                'demissionReasonId'             => $itemAusencia->MOTIVO == NULL ? NULL : intval($itemAusencia->ID_MOTIVO_DEMISSAO)
            );
            
            // var_dump($itemAusencia->FINISHDATETIME);
            // var_dump($arrayAusencia);
            // exit;
            $ret = $this->enviaAusencia($arrayAusencia, $itemAusencia);
            sleep(1);
        }

        $this->info("Total de {$nAusencias} ausencia(s) cadastrados!");
    }

    private function enviaAusencia($array, $itemAusencia)
    {
        //var_dump($itemAusencia);exit;
        $colab = $itemAusencia->PERSONEXTERNALID;
        $tipoAusencia = $itemAusencia->ABSENCESITUATIONEXTERNALID;

        $this->info("Enviando ausência {$tipoAusencia} para colaborador {$colab}");
        
        $itemEnviar = $array;
        
        //var_dump(json_encode($itemEnviar));

        $response = $this->restClient->post("absences", [], [
            'json' => $itemEnviar
        ])->getResponse();

        $responseData = $this->restClient->getResponseData();
        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema na ausência {$tipoAusencia} para colaborador {$colab}: {$errors}");
            $this->atualizaRegistroComErro($itemAusencia, 2, $errors);
        } else {
            $this->info("Ausencia {$tipoAusencia} para colaborador {$colab} enviada com sucesso.");
            $id = $responseData['value']['id']??0;
            $this->atualizaRegistro($itemAusencia, 1, $id);
        }
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

    private function atualizaRegistro($ausencia, $situacao, $id)
    {   
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado.';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO 
            SET ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.ID = '{$id}' 

            WHERE ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.NUMEMP = {$ausencia->NUMEMP}
            AND ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.TIPCOL = {$ausencia->TIPCOL}
            AND ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.NUMCAD = {$ausencia->NUMCAD}
            AND ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.STARTDATETIME = '{$ausencia->STARTDATETIME}'
        ");
    }

    private function atualizaRegistroComErro($ausencia, $situacao, $obs = '')
    {           
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO 
            SET ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.OBSERVACAO = '{$obs}' 

            WHERE ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.NUMEMP = {$ausencia->NUMEMP}
            AND ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.TIPCOL = {$ausencia->TIPCOL}
            AND ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.NUMCAD = {$ausencia->NUMCAD}
            AND ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.STARTDATETIME = '{$ausencia->STARTDATETIME}'
        ");
    }

    // EXCLUIR

    private function formatarData($data)
    {
        $data = $data[4].$data[5].$data[6].$data[7] . '-' . $data[2].$data[3] . '-' .$data[0].$data[1]. ' '.$data[8].$data[9].':'.$data[10].$data[11].':'.$data[12].$data[13];
        return $data;
    }
    
    private function getAusenciasExcluir()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.TIPO = 3 
            AND (".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.SITUACAO = 0 OR 
            ".env('DB_OWNER')."NEXTI_AUSENCIAS_SENIOR_DEMISSAO.SITUACAO = 2)
        ";
        $ausencias = DB::connection('sqlsrv')->select($this->query . $where);

        return $ausencias;
    }

    private function buscarAusenciasExcluir()
    {
        $ausencias = $this->getAusenciasExcluir();
        $nAusencias = sizeof($ausencias);
        $this->info("Iniciando exclusão de dados ({$nAusencias} ausências)");

        foreach ($ausencias as $itemAusencia) {
            $ret = $this->enviaAusenciaExcluir($itemAusencia);
            sleep(1);
        }

        $this->info("Total de {$nAusencias} ausência(s) cadastrados!");
    }

    private function enviaAusenciaExcluir($ausencia)
    {
        $this->info("Excluindo ausência: {$ausencia->EXTERNALID}");
        $response = $this->restClient->delete("absences/" . $ausencia->ID, [], [])->getResponse();
        
        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ausencia: {$ausencia->PERSONEXTERNALID}: {$errors}");

            $this->atualizaRegistroComErro($ausencia, 2, $errors);
        } else {
            $this->info("Exclusão de ausência: {$ausencia->PERSONEXTERNALID} excluída com sucesso.");
            $responseData = $this->restClient->getResponseData();
            
            $this->atualizaRegistro($ausencia, 1, $ausencia->ID);
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
        $this->buscarAusenciasExcluir();
        $this->buscarAusenciasNovos();
    }
}
