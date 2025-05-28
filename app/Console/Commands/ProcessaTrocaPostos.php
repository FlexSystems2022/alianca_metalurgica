<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helper\LogColaborador;

class ProcessaTrocaPostos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTrocaPostos';

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
                FLEX_TROCA_POSTO_PROTHEUS.*,
                FLEX_POSTO.IDEXTERNO AS IDEXTERNOPOSTO,
                FLEX_COLABORADOR.IDEXTERNO AS IDEXTERNOCOLABORADOR
            FROM ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS
            JOIN ".env('DB_OWNER')."FLEX_POSTO
                ON (
                    FLEX_POSTO.NUMEMP = FLEX_TROCA_POSTO_PROTHEUS.NUMEMP
                    AND FLEX_POSTO.CODCCU = FLEX_TROCA_POSTO_PROTHEUS.CODCCU
                )
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR
                ON(
                    FLEX_COLABORADOR.IDEXTERNO = FLEX_TROCA_POSTO_PROTHEUS.IDEXTERNO
                )
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

    private function atualizaRegistro($idExterno, $situacao, $id, $trocaOriginal)
    {
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado.';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS 
            SET ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.IDEXTERNO = {$trocaOriginal->IDEXTERNO}
            AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.POSTRA = '{$trocaOriginal->POSTRA}'
            AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.CODCCU = '{$trocaOriginal->CODCCU}'
            AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.INIATU = CONVERT(DATETIME,'{$trocaOriginal->INIATU}',120)
        ");
    }

    private function atualizaRegistroComErro($idExterno, $situacao, $obs = '', $trocaOriginal)
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS 
            SET ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.IDEXTERNO = {$trocaOriginal->IDEXTERNO}
            AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.POSTRA = '{$trocaOriginal->POSTRA}'
            AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.CODCCU = '{$trocaOriginal->CODCCU}'
            AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.INIATU = CONVERT(DATETIME,'{$trocaOriginal->INIATU}',120)
        ");
    }

    private function getTrocaPostosNovos()
    {
        $where = "  
            WHERE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.SITUACAO IN (0,2)
            ORDER BY ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.INIATU DESC
        ";
        
        $trocaPostos = DB::connection('sqlsrv')->select($this->query . $where);

        return $trocaPostos;
    }

    private function enviaTrocaPostosNovos()
    {
        $trocaPostos = $this->getTrocaPostosNovos();
        $nTrocaPostos = sizeof($trocaPostos);
        $this->info("Iniciando envio de dados ({$nTrocaPostos} postos)");

        foreach ($trocaPostos as $trocaPosto) {
            $trocaPostoNexti = [
                'transferDateTime' => date('dmYHis', strtotime($trocaPosto->INIATU)),
                'personExternalId' => $trocaPosto->IDEXTERNOCOLABORADOR,
                'workplaceExternalId' => $trocaPosto->IDEXTERNOPOSTO
            ];

            $ret = $this->enviaTrocaPostoNovo($trocaPostoNexti, $trocaPosto);
            sleep(2);
        }
        $this->info("Total de {$nTrocaPostos} trocaPosto(s) cadastrados!");
    }

    private function enviaTrocaPostoNovo($trocaPosto, $trocaOriginal)
    {
        $workplaceExternalId = $trocaPosto['workplaceExternalId'];
        $idCol = $trocaPosto['personExternalId'];

        $this->info("Enviando troca de posto: {$workplaceExternalId} para colaborador {$idCol}");

        $response = $this->restClient->post("workplacetransfers", [], [
            'json' => $trocaPosto
        ])->getResponse();

        // \App\Helper\LogColaborador::init()
        //     ->empresa($trocaOriginal->NUMEMP)
        //     ->matricula($trocaOriginal->NUMCAD)
        //     ->processo('Criação de Troca Postos')
        //     ->uri("workplacetransfers")
        //     ->payload($trocaPosto)
        //     ->response([
        //         'status' => $this->restClient->getResponse()->getStatusCode(),
        //         'data' => $this->restClient->getResponseData()
        //     ])
        //     ->save();

        if (!$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema troca de posto: {$workplaceExternalId} para colaborador {$idCol}: {$errors}");

            $this->atualizaRegistroComErro($trocaOriginal->IDEXTERNO, 2, $errors, $trocaOriginal);
        } else {
            $this->info("Troca de posto: {$workplaceExternalId} para colaborador {$idCol} enviada com sucesso.");
            
            $responseData = $this->restClient->getResponseData();
            $trocaPostoInserido = $responseData['value'];
            $id = $trocaPostoInserido['id'];

            $this->atualizaRegistro($trocaOriginal->IDEXTERNO, 1, $id, $trocaOriginal);
        }
    }



    private function getTrocaPostosExcluir()
    {
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.TIPO = 3 
            AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.SITUACAO IN (0,2)
            --AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.TIPO_TROCA = 'A'
        ";
        
        $trocaPostos = DB::connection('sqlsrv')->select($query);

        return $trocaPostos;
    }

    private function enviaTrocaPostosExcluir()
    {
        $trocaPostos = $this->getTrocaPostosExcluir();
        $nTrocaPostos = sizeof($trocaPostos);
        $this->info("Iniciando exclusão de dados ({$nTrocaPostos} trocas de postos)");

        foreach ($trocaPostos as $trocaPosto) {
            $trocaPostoNexti = [
                'id' => $trocaPosto->ID,
            ];
            
            $ret = $this->enviaTrocaPostoExcluir($trocaPostoNexti, $trocaPosto);
            sleep(2);
        }

        $this->info("Total de {$nTrocaPostos} trocaPosto(s) cadastrados!");
    }

    private function enviaTrocaPostoExcluir($trocaPosto, $trocaOriginal)
    {
        
        $this->info("Excluindo troca de posto");
        $response = $this->restClient->delete("workplacetransfers/{$trocaPosto['id']}", [], [])->getResponse();

        // \App\Helper\LogColaborador::init()
        //     ->empresa($trocaOriginal->NUMEMP)
        //     ->matricula($trocaOriginal->NUMCAD)
        //     ->processo('Exclusão de Troca Postos')
        //     ->uri("workplacetransfers/" . $trocaOriginal->ID)
        //     ->response([
        //         'status' => $this->restClient->getResponse()->getStatusCode(),
        //         'data' => $this->restClient->getResponseData()
        //     ])
        //     ->save();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema troca de posto: {$errors}");
            $this->atualizaRegistroComErro($trocaOriginal->IDEXTERNO, 2, $errors, $trocaOriginal);
        } else {
            $this->info("Exclusão de troca de posto enviada com sucesso.");
            $this->atualizaRegistro($trocaOriginal->IDEXTERNO, 1, $trocaPosto['id'], $trocaOriginal);
            $this->removeItemBanco($trocaPosto['id']);
        }
    }

    private function removeItemBanco($id)
    {
        $sql = "DELETE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS WHERE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.ID = {$id}";
        DB::connection('sqlsrv')->statement($sql);


        $sql = "DELETE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.ID = {$id}";
        DB::connection('sqlsrv')->statement($sql);
    }


    private function insertLogs($msg, $dataInicio, $dataFim)
    {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime,'{$dataInicio}',120), convert(datetime,'{$dataFim}',120), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }

    private function limpaTabela(){
        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS WHERE TIPO = 3 AND SITUACAO = 1
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function sanearExclusoesJaRealizadas(){
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS
            SET ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.SITUACAO = 1
            WHERE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.SITUACAO IN(0,2)
            AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.TIPO = 3
            AND EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS
                WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.ID = ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.ID
                AND ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.TIPO = 3
            )
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function getTrocaPostosExcluirConflitos(){
        $query = 
        "
            SELECT 
            ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.ID,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP
            FROM ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR
                ON ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO = ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.PERSONEXTERNALID
            WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.TIPO = 0
            AND EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.IDEXTERNO = ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.INIATU = ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.TRANSFERDATETIME
                AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.TIPO = 0
                AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.SITUACAO IN(0,2)
            )
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function enviaTrocaPostosExcluirConflitos()
    {
        $trocaPostos = $this->getTrocaPostosExcluirConflitos();
        $nTrocaPostos = sizeof($trocaPostos);
        $this->info("Iniciando exclusão de dados ({$nTrocaPostos} trocas de postos)");

        foreach ($trocaPostos as $trocaPosto) {
            $this->enviaTrocaPostoExcluirConflitos($trocaPosto);
            //sleep(2);
        }

        $this->info("Total de {$nTrocaPostos} trocaPosto(s) cadastrados!");
    }

    private function enviaTrocaPostoExcluirConflitos($trocaPosto)
    {
        
        $this->info("Excluindo troca de posto");
        $response = $this->restClient->delete("workplacetransfers/{$trocaPosto->ID}", [], [])->getResponse();

        \App\Helper\LogColaborador::init()
            ->empresa($trocaPosto->NUMEMP)
            ->matricula($trocaPosto->NUMCAD)
            ->processo('Exclusão de Troca Postos Conflitos')
            ->uri("workplacetransfers/" . $trocaPosto->ID)
            ->response([
                'status' => $this->restClient->getResponse()->getStatusCode(),
                'data' => $this->restClient->getResponseData()
            ])
            ->save();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema troca de posto: {$errors}");
        } else {
            $this->info("Exclusão de troca de posto enviada com sucesso.");
            $this->removeItemBanco($trocaPosto->ID);
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

        //$this->sanearExclusoesJaRealizadas();
        //$this->enviaTrocaPostosExcluirConflitos();
        $this->enviaTrocaPostosExcluir();
        $this->limpaTabela();
        $this->enviaTrocaPostosNovos();

        //$this->enviaTrocaCoberuraNovos();
        //$this->enviaTrocaCoberturaxcluir();
    }
}