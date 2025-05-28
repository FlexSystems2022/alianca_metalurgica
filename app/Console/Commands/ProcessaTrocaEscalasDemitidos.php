<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaTrocaEscalasDemitidos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTrocaEscalasDemitidos';

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
            *
            FROM ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX
        ";
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

    private function getTrocaEscalasNovos()
    {
        $where =
        " 
            WHERE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TIPO = 0 
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO IN (99)
            ORDER BY ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT DESC
        ";
        $trocaEscalas = DB::connection('sqlsrv')->select($this->query . $where);
        return $trocaEscalas;
    }

    private function enviaTrocaEscalasNovos()
    {
        $trocaEscalas = $this->getTrocaEscalasNovos();
        $nTrocaEscalas = sizeof($trocaEscalas);
        $this->info("Iniciando envio de dados ({$nTrocaEscalas} escalas)");

        foreach ($trocaEscalas as $trocaEscala) {
            $trocaEscalaNexti = [
                'transferDateTime'      => date('dmYHis', strtotime($trocaEscala->DATALT)),
                'personExternalId'      => $trocaEscala->IDEXTERNO,
                'scheduleExternalId'    => $trocaEscala->ESCALA,
                'rotationCode'          => intval($trocaEscala->TURMA)??1
            ];

            //var_dump($trocaEscalaNexti);exit;
            $ret = $this->processaTrocaEscalaNovo($trocaEscalaNexti, $trocaEscala);
            sleep(2);
        }
        $this->info("Total de {$nTrocaEscalas} trocaEscala(s) cadastrados!");
    }

    private function processaTrocaEscalaNovo($trocaEscala, $trocaOriginal)
    {
        $scheduleExternalId = $trocaEscala['scheduleExternalId'];
        $idCol = $trocaEscala['personExternalId'];

        //var_dump($trocaEscala);exit;

        $this->info("Enviando troca de escala: {$scheduleExternalId} para colaborador {$idCol}");

        $response = $this->restClient->post("scheduletransfers", [], [
            'json' => $trocaEscala
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $resposta = $this->restClient->getResponseData();

            $errors = $this->getResponseErrors();
            $this->info("Problema troca de escala: {$scheduleExternalId} para colaborador {$idCol}: {$errors}");
            $idCol = $trocaEscala['personExternalId'];
            $idEscala = $trocaEscala['scheduleExternalId'];
            $turma = $trocaEscala['rotationCode'];
            $datalt = $trocaOriginal->DATALT;

            $this->atualizaRegistroComErro($idEscala, $idCol, $datalt, $turma, 2, $errors);
        } else {
            $this->info("Troca de escala: {$scheduleExternalId} para colaborador {$idCol} enviada com sucesso.");
            
            $responseData = $this->restClient->getResponseData();
            $trocaEscalaInserido = $responseData['value'];
            $id = $trocaEscalaInserido['id'];
            $idCol = $trocaEscala['personExternalId'];
            $idEscala = $trocaEscala['scheduleExternalId'];
            $datalt = $trocaOriginal->DATALT;
            $turma = $trocaEscala['rotationCode'];

            $this->atualizaRegistro($idEscala, $idCol, $datalt, $turma, 1, $id);
        }
    }

    private function atualizaRegistro($idescala, $idcol, $datalt, $turma, $situacao, $id)
    {
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado.';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX 
            SET ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ESCALA = '{$idescala}' 
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.IDEXTERNO = '{$idcol}' 
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT = '{$datalt}'
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TURMA = '{$turma}'
        ");
    }

    private function atualizaRegistroComErro($idescala, $idcol, $datalt, $turma, $situacao, $obs = '')
    {   
        $obs = str_replace("'", '', $obs);
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX 
            SET ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO = 99, 
            ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ESCALA = '{$idescala}' 
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.IDEXTERNO = '{$idcol}' 
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT = '{$datalt}'
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TURMA = '{$turma}'
        ");
    }

    private function getTrocaEscalasExcluir()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TIPO = 3 
            AND (".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO = 0 
            OR ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO = 2)
        ";
        $trocaEscalas = DB::connection('sqlsrv')->select($this->query . $where);

        return $trocaEscalas;
    }


    private function processaTrocaEscalasExcluir()
    {
        $trocaEscalas = $this->getTrocaEscalasExcluir();
        $nTrocaEscalas = sizeof($trocaEscalas);
        $this->info("Iniciando exclusão de dados ({$nTrocaEscalas} trocas de escalas)");

        foreach ($trocaEscalas as $trocaEscala) {
            // $trocaEscalaNexti = [
            //     'lastUpdate' => date('dmYhis'),
            //     'transferDateTime'  => implode('', array_reverse(explode('-', $trocaEscala->DATALT))) . '000001',
            //     'personExternalId'  => $trocaEscala->IDEXTERNO,
            //     'scheduleExternalId'=> $trocaEscala->ESCALA,
            //     'rotationCode'      => ($trocaEscala->TIPESC == 'I' ? 1 : intval($trocaEscala->TURMA)),
            //     'id'                => $trocaEscala->ID
            // ];
            $trocaEscalaNexti = [
                'id'                => $trocaEscala->ID
            ];
            $ret = $this->enviaTrocaEscalaExcluir($trocaEscalaNexti, $trocaEscala);
            //sleep(2);
        }
        $this->info("Total de {$nTrocaEscalas} trocaEscala(s) cadastrados!");
    }

    private function enviaTrocaEscalaExcluir($trocaEscala, $trocaOriginal)
    {
        $scheduleExternalId = $trocaOriginal->ESCALA;
        $idCol = $trocaOriginal->IDEXTERNO;

        $this->info("Excluindo troca de escala: {$scheduleExternalId} para colaborador {$idCol}");

        $response = $this->restClient->delete("scheduletransfers/" . $trocaEscala['id'], [], [])->getResponse();
        
        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema troca de escala: {$scheduleExternalId} para colaborador {$idCol}: {$errors}");
            $idCol = $trocaOriginal->IDEXTERNO;
            $idEscala = $trocaOriginal->ESCALA;
            $datalt = $trocaOriginal->DATALT;
            $turma = $trocaOriginal->TURMA;

            $this->atualizaRegistroComErro($idEscala, $idCol, $datalt, $turma, 2, $errors);
        } else {
            $this->info("Exclusão de troca de escala: {$scheduleExternalId} para colaborador {$idCol} enviada com sucesso.");
            
            $responseData = $this->restClient->getResponseData();

            $trocaEscalaInserido = $responseData['value'];
            $id = $trocaEscalaInserido['id'];
            $idCol = $trocaOriginal->IDEXTERNO;
            $idEscala = $trocaOriginal->ESCALA;
            $datalt = $trocaOriginal->DATALT;
            $turma = $trocaOriginal->TURMA;

            $this->atualizaRegistro($idEscala, $idCol, $datalt, $turma, 1, $trocaEscala['id']);
        }
    }

    private function insertLogs($msg, $dataInicio, $dataFim)
    {
        $sql = "INSERT INTO ".env('DB_OWNER')."NEXTI_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime,'{$dataInicio}',120), convert(datetime,'{$dataFim}',120), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }

    public function corrigeRegistrosDiferentesNextiFlex()
    {
        $query =
        "
            SELECT
            * 
            FROM ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX
            WHERE SITUACAO = 1 AND TIPO = 3
        ";

        $registros = DB::connection('sqlsrv')->select($query);
        if (sizeof($registros) > 0) {
            foreach ($registros as $itemRegistro) {
                //if (explode(' ', $itemRegistro->fimSenior)[0] != explode(' ', $itemRegistro->fimNexti)[0]) {
                    $update ="DELETE FROM " .env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX WHERE ID = {$itemRegistro->ID}";
                    DB::connection('sqlsrv')->statement($update);

                    $delete = "DELETE FROM " .env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS WHERE ID = {$itemRegistro->ID}";
                    DB::connection('sqlsrv')->statement($delete);
                //}
            }
        }
    }

    private function desconsiderarIguais(){
        $query = 
        "
            UPDATE " .env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX
            SET " .env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO = 1
            WHERE " .env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO IN(0,2)
            --AND " .env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT >= CONVERT(DATETIME, '2024-06-01', 120)
            AND " .env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.OBSERVACAO LIKE '%Escala e turma iguais ao anterior%'
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function excluiNaoExistentes(){
        $query = 
        "
            DELETE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX
            WHERE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO IN(0,2)
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TIPO = 0
            AND NOT EXISTS(
                SELECT * FROM Protheus12_producao.dbo.SPF010
                JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR
                    ON ".env('DB_OWNER')."NEXTI_COLABORADOR.NUMEMP = 1
                    AND ".env('DB_OWNER')."NEXTI_COLABORADOR.CODFIL = SPF010.PF_FILIAL collate database_default
                    AND ".env('DB_OWNER')."NEXTI_COLABORADOR.NUMCAD = SPF010.PF_MAT collate database_default
                    
                WHERE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.NUMEMP = ".env('DB_OWNER')."NEXTI_COLABORADOR.NUMEMP
                AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.CODFIL = ".env('DB_OWNER')."NEXTI_COLABORADOR.CODFIL
                AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TIPCOL = ".env('DB_OWNER')."NEXTI_COLABORADOR.TIPCOL
                AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.NUMCAD = ".env('DB_OWNER')."NEXTI_COLABORADOR.NUMCAD
                AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT = SPF010.PF_DATA
                AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TURMA = CAST(SPF010.PF_SEQUEPA AS INT)
                AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ESCALA = ('01' + CAST(SPF010.PF_FILIAL AS VARCHAR(100)) + CAST(SPF010.PF_TURNOPA AS VARCHAR(100))) collate database_default
                AND  Protheus12_producao.dbo.SPF010.D_E_L_E_T_ = ''
            )
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.NUMEMP = 1
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ORIGEM = 'H'
        ";

        DB::connection('sqlsrv')->statement($query);

        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."NEXTI_COLABORADOR
                WHERE ".env('DB_OWNER')."NEXTI_COLABORADOR.IDEXTERNO = ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.IDEXTERNO
                AND ".env('DB_OWNER')."NEXTI_COLABORADOR.DATADMISSAO > ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT
            )
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ID = 0
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
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        // $this->excluiNaoExistentes();
        // $this->processaTrocaEscalasExcluir();
        // $this->corrigeRegistrosDiferentesNextiFlex();
        // $this->desconsiderarIguais();
        $this->enviaTrocaEscalasNovos();
        
        $dataFim = date('Y-m-d H:i:s A');
        //$this->insertLogs("Trocas de escala foram enviadas para o Nexti.", $dataInicio, $dataFim);
    }
}
