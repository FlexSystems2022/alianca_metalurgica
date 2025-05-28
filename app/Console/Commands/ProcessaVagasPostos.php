<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaVagasPostos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaVagasPostos';

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
            ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.*,
            ".env('DB_OWNER').".dbo.NEXTI_POSTO.ID AS IDPOSTO,
            ".env('DB_OWNER').".dbo.NEXTI_POSTO.EXTERNALID AS IDEXTERNOPOSTO,
            ".env('DB_OWNER').".dbo.NEXTI_CARGO.ID AS IDCARGO,
            ".env('DB_OWNER').".dbo.NEXTI_CARGO.EXTERNALID AS IDEXTERNOCARGO,
            ".env('DB_OWNER').".dbo.NEXTI_CARGO.NAME AS NOMECARGO
            FROM ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS 
            JOIN ".env('DB_OWNER').".dbo.NEXTI_POSTO
                ON ".env('DB_OWNER').".dbo.NEXTI_POSTO.ESTPOS = ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.ESTPOS
                AND ".env('DB_OWNER').".dbo.NEXTI_POSTO.POSTRA = ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.POSTRA
            JOIN ".env('DB_OWNER').".dbo.NEXTI_CARGO
                ON ".env('DB_OWNER').".dbo.NEXTI_CARGO.ESTCAR = ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.ESTCAR
                AND ".env('DB_OWNER').".dbo.NEXTI_CARGO.CODCAR = ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.CODCAR
        ";
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

    private function atualizaRegistro($vaga, $situacao, $id, $payload="")
    {
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado';
        $data = date("Y-m-d", strtotime($vaga->CMPQUA));
        DB::connection('sqlsrv')->statement("UPDATE ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS 
            SET ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.PAYLOAD = '{$payload}',
            ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.ID = '{$id}' 
            WHERE ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.TABORG = {$vaga->TABORG} 
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.NUMLOC = {$vaga->NUMLOC}
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.ESTCAR = {$vaga->ESTCAR}
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.CODCAR = {$vaga->CODCAR}
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.ESTPOS = {$vaga->ESTPOS}
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.POSTRA = '{$vaga->POSTRA}'
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.CMPQUA = '{$data}' ");
    }

    private function atualizaRegistroComErro($vaga, $situacao, $obs = '', $payload="")
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        $data = date("Y-m-d", strtotime($vaga->CMPQUA));
        DB::connection('sqlsrv')->statement("UPDATE ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS 
            SET ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.OBSERVACAO = '{$obs}',
            ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.PAYLOAD = '{$payload}' 
            WHERE ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.TABORG = {$vaga->TABORG} 
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.NUMLOC = {$vaga->NUMLOC}
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.ESTCAR = {$vaga->ESTCAR}
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.CODCAR = {$vaga->CODCAR}
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.ESTPOS = {$vaga->ESTPOS}
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.POSTRA = '{$vaga->POSTRA}'
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.CMPQUA = '{$data}' ");
    }

    private function buscaVagasPostoNovas()
    {
        $where = "  
            WHERE ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.TIPO = 0 
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.SITUACAO IN (0,2)
            ORDER BY ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.TABORG, 
            ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.NUMLOC, 
            ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.CMPQUA
        ";

        $vagas = DB::connection('sqlsrv')->select($this->query . $where);

        return $vagas;
    }

    private function enviaVagasPostoNovas()
    {
        $vagas = $this->buscaVagasPostoNovas();

        $nVagas = sizeof($vagas);
        $this->info("Iniciando envio de dados ({$nVagas} vagas de postos)");

        foreach ($vagas as $vaga) {
            $cargo = array();
            $turno = array();

            $arrayCargo = array(
                //'externalId'    => $vaga->IDEXTERNOCARGO,
                'id'            => intval($vaga->IDCARGO),
                'name'          => trim($vaga->NOMECARGO)
            );

            $arrayTurno = array(
                'id' => intval(env("Turno".$vaga->TURNO."Nexti")),
            );

            $cargo[] = $arrayCargo;
            $turno[] = $arrayTurno;
            $vagaPostoNexti = [
                'startDateTime'         => date('dmYHis', strtotime($vaga->CMPQUA)),
                'workplaceExternalId'   => $vaga->IDEXTERNOCARGO,
                'workplaceId'           => intval($vaga->IDPOSTO),
                'quantity'              => intval($vaga->VAGAS),
                'careerDtoList'         => $cargo
            ];
            if($vaga->DATAFIM == '31-12-1900' || $vaga->DATAFIM == null){
                $vagaPostoNexti['finishDateTime']  = null;
            }else{
                $vagaPostoNexti['finishDateTime']  = date('dmYHis', strtotime($vaga->DATAFIM));
            }

            var_dump($vagaPostoNexti);exit;

            //$ret = $this->enviaVagaPostoNova($vagaPostoNexti, $vaga);
        }
        $this->info("Total de {$nVagas} trocaPosto(s) cadastrados!");
    }

    private function enviaVagaPostoNova($vagaPostoNexti, $vaga)
    {
        $payload = json_encode($vagaPostoNexti);
        $workplaceExternalId = $vagaPostoNexti['workplaceExternalId'];

        $this->info("Enviando vaga para o posto: {$workplaceExternalId}");

        $response = $this->restClient->post("workplacevacancies", [], [
            'json' => $vagaPostoNexti
        ])->getResponse();

        //var_dump($response);exit;

        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema vaga de posto: {$workplaceExternalId}: {$errors}");

            $this->atualizaRegistroComErro($vaga, 2, $errors, $payload);
        } else {
            $this->info("Vaga de posto: {$workplaceExternalId} enviada com sucesso.");
            
            $responseData = $this->restClient->getResponseData();
            $vagaInserida = $responseData['value'];
            $id = $vagaInserida['id'];

            $this->atualizaRegistro($vaga, 1, $id, $payload);
        }
    }

    private function buscaVagasPostoExcluir()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.TIPO = 3 
            AND ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.SITUACAO IN(0,2)
        ";
        
        $vagas = DB::connection('sqlsrv')->select($this->query . $where);

        return $vagas;
    }

    private function enviaVagasPostoExcluir()
    {
        $vagas = $this->buscaVagasPostoExcluir();
        $nVagas = sizeof($vagas);
        $this->info("Iniciando exclusão de dados ({$nVagas} vagas de posto)");

        foreach ($vagas as $vaga) {
            $vagaNexti = [
                'id'        => $vaga->ID,
            ];
            $ret = $this->enviaVagaPostoExcluir($vagaNexti, $vaga);
        }

        $this->info("Total de {$nVagas} vaga(s) cadastrados!");
    }

    private function enviaVagaPostoExcluir($vagaNexti, $vaga)
    {
        $this->info("Excluindo vaga de posto: {$vaga->IDEXTERNOPOSTO}");

        $response = $this->restClient->delete("workplacevacancies/{$vagaNexti['id']}", [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema vaga de posto: {$vaga->IDEXTERNOPOSTO}: {$errors}");

            $this->atualizaRegistroComErro($vaga, 2, $errors);
        } else {
            $this->info("Exclusão de vaga de posto: {$vaga->IDEXTERNOPOSTO} enviada com sucesso.");
            //$this->removeItemBanco($vagaNexti['id']);
            $this->atualizaRegistro($vaga, 1, $vagaNexti['id']);
        }
    }

    // private function removeItemBanco($id)
    // {
    //     $sql = "DELETE ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS WHERE ".env('DB_OWNER').".dbo.NEXTI_POSTO_VAGAS.ID = {$id}";
    //     DB::connection('sqlsrv')->statement($sql);
    //     DB::connection('sqlsrv')->statement("COMMIT");
    // }

    // private function insertLogs($msg, $dataInicio, $dataFim) {
    //     $sql = "INSERT INTO ".env('DB_OWNER').".dbo.NEXTI_LOG (ID_LOG, DATA_INICIO, DATA_FIM, MSG)
    //             VALUES (ID_LOG.NEXTVAL, TO_DATE('{$dataInicio}', 'YYYY-MM-DD HH24:MI:SS'), TO_DATE('{$dataFim}', 'YYYY-MM-DD HH24:MI:SS'), '{$msg}')";
    //     DB::connection('sqlsrv')->statement($sql);
    //     DB::connection('sqlsrv')->statement("COMMIT");
    // }

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

        $retTrocaPostosExcluir = $this->enviaVagasPostoExcluir();
        $retTrocaPostosNovos = $this->enviaVagasPostoNovas();
        
        $dataFim = date('Y-m-d H:i:s');
        //$this->insertLogs("Trocas de posto foram enviadas para o Nexti.", $dataInicio, $dataFim);
    }
}
