<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaCoberturas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaCoberturas';

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
        $this->query = "
        SELECT 
            *
        FROM ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS";
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

    private function atualizaRegistro($trocaOriginal, $situacao, $id)
    {
        $observacao = date('d/m/Y H:i:s') . ' Registro criado/atualizado com sucesso';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS 
            SET ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.OBSERVACAO = '{$observacao}', 
            ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.ID = '{$id}' 
            WHERE flexsystemsnexti.dbo.NEXTI_COBERTURAS.PERSONEXTERNALID = '{$trocaOriginal->PERSONEXTERNALID}'
            AND flexsystemsnexti.dbo.NEXTI_COBERTURAS.WORKPLACEEXTERNALID = '{$trocaOriginal->WORKPLACEEXTERNALID}'
            AND flexsystemsnexti.dbo.NEXTI_COBERTURAS.STARTDATETIME = '{$trocaOriginal->STARTDATETIME}'
            AND flexsystemsnexti.dbo.NEXTI_COBERTURAS.STARTMINUTE = '{$trocaOriginal->STARTMINUTE}'
            AND flexsystemsnexti.dbo.NEXTI_COBERTURAS.FINISHDATETIME = '{$trocaOriginal->FINISHDATETIME}'
            AND flexsystemsnexti.dbo.NEXTI_COBERTURAS.FINISHMINUTE = '{$trocaOriginal->FINISHMINUTE}'
            ");
    }

    private function atualizaRegistroComErro($trocaOriginal, $situacao, $obs = '')
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS 
            SET ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.OBSERVACAO = '{$obs}'
            WHERE flexsystemsnexti.dbo.NEXTI_COBERTURAS.PERSONEXTERNALID = '{$trocaOriginal->PERSONEXTERNALID}'
            AND flexsystemsnexti.dbo.NEXTI_COBERTURAS.WORKPLACEEXTERNALID = '{$trocaOriginal->WORKPLACEEXTERNALID}'
            AND flexsystemsnexti.dbo.NEXTI_COBERTURAS.STARTDATETIME = '{$trocaOriginal->STARTDATETIME}'
            AND flexsystemsnexti.dbo.NEXTI_COBERTURAS.STARTMINUTE = '{$trocaOriginal->STARTMINUTE}'
            AND flexsystemsnexti.dbo.NEXTI_COBERTURAS.FINISHDATETIME = '{$trocaOriginal->FINISHDATETIME}'
            AND flexsystemsnexti.dbo.NEXTI_COBERTURAS.FINISHMINUTE = '{$trocaOriginal->FINISHMINUTE}'
            ");
    }

    private function getTrocasHorariosNovos()
    {
        $where = "  WHERE ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.TIPO = 0 
        AND ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.SITUACAO IN (0,2)
        ORDER BY ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.STARTDATETIME DESC";

        $trocas = DB::connection('sqlsrv')->select($this->query . $where);
        return $trocas;
    }

    private function getTrocasHorariosExcluir()
    {
        $where = "  WHERE ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.TIPO = 3 
        AND (".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.SITUACAO = 0 
        OR ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.SITUACAO = 2)";
        
        $trocaPostos = DB::connection('sqlsrv')->select($this->query . $where);

        return $trocaPostos;
    }

    private function enviaTrocaHorarioNovos()
    {
        $this->info("Iniciando envio:");
        $trocas  = $this->getTrocasHorariosNovos();
        $nTrocas = sizeof($trocas);   
        //var_dump($trocas);exit;
        foreach ($trocas as $troca) {
            $startMinute = explode(':', $troca->STARTMINUTE);
            $startMinute = ($startMinute[0] * 60) + $startMinute[1];
            $finishMinute = explode(':', $troca->FINISHMINUTE);
            $finishMinute = ($finishMinute[0] * 60) + $finishMinute[1];
            $trocaNexti = [
                'finishDateTime'        => date("dmYHis", strtotime($troca->STARTDATETIME)),
                'startDateTime'         => date("dmYHis", strtotime($troca->FINISHDATETIME)),
                'personExternalId'      => $troca->PERSONEXTERNALID,
                'personId'              => $troca->PERSONID,
                'replacementTypeId'     => $troca->TIPOCOBERTURA,
                'workplaceExternalId'   => $troca->WORKPLACEEXTERNALID,
                'startMinute'           => $startMinute,
                'finishMinute'          => $finishMinute
            ];

            //echo json_encode($trocaNexti, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            //dd($trocaNexti);
            $ret = $this->enviaTrocaHorarioNovo($trocaNexti, $troca);
            sleep(2);
        }
        $this->info("Total de {$nTrocas} trocas de horario(s) cadastrados!");
    }

    private function enviaTrocaHorarioNovo($troca, $trocaOriginal)
    {   
        $name = $troca['personExternalId'];
        $this->info("Enviando a troca de horários para {$name}");
        
        $response = $this->restClient->post("replacements", [], [
            'json' => $troca
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            if ($this->restClient->isResponseStatusCode(412)) {
                $errors = 'Falha nos campos obrigatórios, verificar! ' . $this->getResponseErrors();
            } else {
                $errors = $this->getResponseErrors();
                $this->info("Problema ao enviar o troca de horário para {$name} para API Nexti: {$errors}");
            }


            $this->atualizaRegistroComErro($trocaOriginal, 2, $errors);
        } else {
            $this->info("Troca de Horário para {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $trocaInserida = $responseData['value'];
            $id = $trocaInserida['id'];

            $this->atualizaRegistro($trocaOriginal, 1, $id);
        }
    }

    private function enviaCoberturasExcluir()
    {
        $trocas = $this->getTrocasHorariosExcluir();
        $nTrocas = sizeof($trocas);
        $this->info("Iniciando exclusão de dados ({$nTrocas} trocas de horarios)");

        foreach ($trocas as $troca) {
            $ret = $this->enviaTrocaHorarioExcluir($troca);
            sleep(2);
        }
        $this->info("Total de {$nTrocas} trocas de horario(s) cadastrados!");
    }

    private function enviaTrocaHorarioExcluir($troca)
    {
        $data = $troca->DATA;
        $idCol = $troca->IDEXTERNOCOLAB;

        $this->info("Excluindo troca de posto: {$data} para colaborador {$idCol}");
        $response = $this->restClient->delete("replacements/{$troca->ID}", [], [])->getResponse();
        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao excluir troca de horario: {$data} para colaborador {$idCol}: {$errors}");

            $this->atualizaRegistroComErro($troca, 2, $errors);
        } else {
            $this->info("Exclusão de troca de horario: {$data} para colaborador {$idCol} enviada com sucesso.");
            $this->atualizaRegistro($troca, 1, $troca->ID);
        }
    }

    private function insertLogs($msg, $dataInicio, $dataFim) {
        $sql = "INSERT INTO ".env('DB_OWNER').".dbo.NEXTI_LOG (DATE, MESSAGE)
        VALUES (convert(datetime,'{$dataInicio}',5), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }

    private function ajusteDemitidos(){
        $query = 
        "
        UPDATE ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS
        SET ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.SITUACAO = 1
        WHERE EXISTS(
            SELECT * FROM ".env('DB_OWNER').".dbo.NEXTI_AUSENCIAS_SENIOR
            WHERE ".env('DB_OWNER').".dbo.NEXTI_AUSENCIAS_SENIOR.IDEXTERNOCOLAB = ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.IDEXTERNOCOLAB
            AND ".env('DB_OWNER').".dbo.NEXTI_AUSENCIAS_SENIOR.IDEXTERNOSITAFA = '7'
            )
        AND ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.SITUACAO IN(0,2)
        AND ".env('DB_OWNER').".dbo.NEXTI_COBERTURAS.TIPO = 0
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
        $dataInicio = date('y-m-d H:i:s A');
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');
        //$this->ajusteDemitidos();
        //$this->enviaCoberturasExcluir();
        $this->enviaTrocaHorarioNovos();
        $dataFim = date('y-m-d H:i:s A');
    }
}
