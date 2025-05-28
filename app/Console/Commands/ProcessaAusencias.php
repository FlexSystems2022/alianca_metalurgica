<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helper\LogColaborador;

class ProcessaAusencias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaAusencias';

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
            SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
        ";
    }

    private function getAusenciaNovos()
    {
        $where =
        " 
            WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN (0,2)
            ORDER BY ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.NUMEMP, 
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.NUMCAD, 
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME
        ";
        $ausencias = DB::connection('sqlsrv')->select($this->query . $where);
        
        return $ausencias;
    }

    private function enviarAusenciasNovas()
    {
        $ausencias = $this->getAusenciaNovos();
        $nAusencias = sizeof($ausencias);
        $this->info("Iniciando envio de dados ({$nAusencias} ausências)");
        
        foreach ($ausencias as $itemAusencia) {
            $arrayAusencia = array(
                "absenceSituationExternalId"    => $itemAusencia->ABSENCESITUATIONEXTERNALID,
                "startDateTime"                 => date("dmY000000", strtotime($itemAusencia->STARTDATETIME)),
                "startMinute"                   => intval($itemAusencia->STARTMINUTE) == 0 ? null : $itemAusencia->STARTMINUTE,
                "personExternalId"              => $itemAusencia->PERSONEXTERNALID,
                "personId"                      => intval($itemAusencia->PERSONID),
            );

            $itemAusencia->FINISHDATETIME = trim($itemAusencia->FINISHDATETIME);
            $itemAusencia->FINISHDATETIME = date("Y-m-d", strtotime($itemAusencia->FINISHDATETIME));

            if($itemAusencia->FINISHDATETIME == '' || $itemAusencia->FINISHDATETIME == NULL || $itemAusencia->FINISHDATETIME == '1900-01-01' || $itemAusencia->FINISHDATETIME == '1900-12-31'){
                $arrayAusencia['finishDateTime'] = NULL;
                $arrayAusencia['finishMinute'] = NULL;
            }else{
                $arrayAusencia['finishDateTime'] = date("dmY000000", strtotime($itemAusencia->FINISHDATETIME));
                $arrayAusencia['finishMinute']   = intval($itemAusencia->FINISHMINUTE) == 0 ? null : $itemAusencia->FINISHMINUTE;
            }
            
            //var_dump($itemAusencia->FINISHDATETIME);
            //var_dump($arrayAusencia);
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

        \App\Helper\LogColaborador::init()
            ->empresa($itemAusencia->NUMEMP)
            ->matricula($itemAusencia->NUMCAD)
            ->processo('Criação de ausência')
            ->uri("absences")
            ->payload($itemEnviar)
            ->response([
                'status' => $this->restClient->getResponse()->getStatusCode(),
                'data' => $this->restClient->getResponseData()
            ])
            ->save();

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
            UPDATE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS 
            SET ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ID = '{$id}' 

            WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.IDEXTERNO = '{$ausencia->IDEXTERNO}'
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME = CONVERT(DATETIME, '{$ausencia->STARTDATETIME}', 120)
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME = CONVERT(DATETIME, '{$ausencia->FINISHDATETIME}', 120)
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '{$ausencia->ABSENCESITUATIONEXTERNALID}'
        ");
    }

    private function atualizaRegistroComErro($ausencia, $situacao, $obs = '')
    {           
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS 
            SET ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.OBSERVACAO = '{$obs}' 

            WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.IDEXTERNO = '{$ausencia->IDEXTERNO}'
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME = CONVERT(DATETIME, '{$ausencia->STARTDATETIME}', 120)
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME = CONVERT(DATETIME, '{$ausencia->FINISHDATETIME}', 120)
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '{$ausencia->ABSENCESITUATIONEXTERNALID}'
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
            WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 3 
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
        ";
        $ausencias = DB::connection('sqlsrv')->select($this->query . $where);

        return $ausencias;
    }

    private function enviarAusenciasExcluir()
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
        $this->info("Excluindo ausência: {$ausencia->IDEXTERNO}");
        $response = $this->restClient->delete("absences/" . $ausencia->ID, [], [])->getResponse();

        \App\Helper\LogColaborador::init()
            ->empresa($ausencia->NUMEMP)
            ->matricula($ausencia->NUMCAD)
            ->processo('Exclusão de ausência')
            ->uri("absences/" . $ausencia->ID)
            ->response([
                'status' => $this->restClient->getResponse()->getStatusCode(),
                'data' => $this->restClient->getResponseData()
            ])
            ->save();
        
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

    private function insertLogs($msg, $dataInicio, $dataFim)
    {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
        VALUES (convert(datetime,'{$dataInicio}',120), convert(datetime,'{$dataFim}',120), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }


    public function corrigeRegistrosDiferentesNextiFlex()
    {
        $query =
        "
            SELECT
                * 
            FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
            WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO = 1 
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 3
        ";

        $registros = DB::connection('sqlsrv')->select($query);
        if (sizeof($registros) > 0) {
            foreach ($registros as $itemRegistro) {
                //if (explode(' ', $itemRegistro->fimSenior)[0] != explode(' ', $itemRegistro->fimNexti)[0]) {
                $update ="DELETE FROM " .env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS WHERE ID = {$itemRegistro->ID}";
                DB::connection('sqlsrv')->statement($update);

                $delete = "DELETE FROM " .env('DB_OWNER')."FLEX_RET_AUSENCIAS WHERE ID = {$itemRegistro->ID}";
                DB::connection('sqlsrv')->statement($delete);
                //}
            }
        }
    }

    private function limpaTabela(){
        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS WHERE TIPO = 3 AND SITUACAO = 1
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function reativaDemissaoExcluidaIndevidamente(){
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
            SET ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0,
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO = 0,
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ID = 0,
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.OBSERVACAO = ''
            WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = '999'
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO = 1
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
            AND EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
                WHERE ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ID
                AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 3
            )
        ";

        DB::connection('sqlsrv')->statement($query);

        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
            SET ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO = 0,
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ID = 0,
            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.OBSERVACAO = ''
            WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO = 1
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
            AND EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
                WHERE ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ID
                AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.REMOVED = 1
            )
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function sanearExclusoesJaRealizadas(){
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
            SET ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO = 1
            WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 3
            AND EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
                WHERE ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ID
                AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 3
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
        $this->sanearExclusoesJaRealizadas();
        $this->reativaDemissaoExcluidaIndevidamente();
        $this->enviarAusenciasExcluir();
        $this->limpaTabela();
        $this->enviarAusenciasNovas();
        $this->corrigeRegistrosDiferentesNextiFlex();
    }
}
