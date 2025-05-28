<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class ProcessaAvisosEpi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaAvisosEpi';

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
                ".env('DB_OWNER')."NEXTI_AVISOS_EPI.*,
                ".env('DB_OWNER')."NEXTI_COLABORADOR.ID AS IDCOLABORADOR,
                ".env('DB_OWNER')."NEXTI_COLABORADOR.NUMCAD AS MATRICULA,
                ".env('DB_OWNER')."NEXTI_COLABORADOR.NOMFUN AS COLABORADOR
            FROM ".env('DB_OWNER')."NEXTI_AVISOS_EPI
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR
                ON ".env('DB_OWNER')."NEXTI_COLABORADOR.NUMEMP = ".env('DB_OWNER')."NEXTI_AVISOS_EPI.NUMEMP
                AND ".env('DB_OWNER')."NEXTI_COLABORADOR.CODFIL = ".env('DB_OWNER')."NEXTI_AVISOS_EPI.CODFIL
                AND ".env('DB_OWNER')."NEXTI_COLABORADOR.TIPCOL = ".env('DB_OWNER')."NEXTI_AVISOS_EPI.TIPCOL
                AND ".env('DB_OWNER')."NEXTI_COLABORADOR.NUMCAD = ".env('DB_OWNER')."NEXTI_AVISOS_EPI.NUMCAD
        ";
    }

    private function getResponseErrors() {
        $responseData = $this->restClient->getResponseData();
       
        $message = $responseData['message']??'';
        $comments = '';
        if(isset($responseData['comments']) && sizeof($responseData['comments']) > 0) {
            $comments = $responseData['comments'][0];
        }
        
        if($comments != '') 
            $message = $comments;

        return $message;
    }

    private function getResponseValue() {
        $responseData = $this->restClient->getResponseData();
        return $responseData;
    }

    private function getInformesNovos()
    {   
        $where = "  
            WHERE ".env('DB_OWNER')."NEXTI_AVISOS_EPI.TIPO = 0 
            AND ".env('DB_OWNER')."NEXTI_AVISOS_EPI.ARQUIVO <> '0'
            AND ".env('DB_OWNER')."NEXTI_AVISOS_EPI.SITUACAO IN (0,2)
        ";

        $informes = DB::connection('sqlsrv')->select($this->query . $where);

        return $informes;
    }

    private function enviaInformesNovos(){
        $this->info("Iniciando envio de dados:");
        $informes = $this->getInformesNovos();
        $nInformes = sizeof($informes);

        $nomeInforme = '';
        $pdfInforme = '';
        $informesNexti = '';
        $header = '';
        foreach ($informes as $informe) {

            $mensagem = 
            "Segue em Anexo a Sua Ficha de EPI!";

            $notices = array(
                "finishDate"            => date("dmY", strtotime("+90days")).'235959',
                "name"                  => 'Ficha de EPI - SA:'. $informe->NUMERO.' - Colaborador:'.$informe->NUMCAD . ' - ' . $informe->COLABORADOR,
                "noticeCollectorId"     => 3,
                "noticeShowId"          => 3,
                "noticeStatusId"        => 1,
                "noticeTypeId"          => 2,
                "persons"               => [intval($informe->IDCOLABORADOR)], 
                "startDate"             => date("dmY") . '000001',
                "text"                  => 'Ficha de EPI - SA:'. $informe->NUMERO.' - Colaborador:'.$informe->NUMCAD . ' - ' . $informe->COLABORADOR,
            );

            $nomeInforme = $informe->COLABORADOR;

            $pdfBase64 = $informe->ARQUIVO;
            $pdfContent = base64_decode($pdfBase64);

            $filePath = "C:\\laragon\\www\\resolv_nexti\\tempAvisos\\documento.pdf";
            file_put_contents($filePath, $pdfContent);

            //unlink($filePath);

            //dd($notices);

            if(file_exists($filePath)){
                $informesNexti = array(
                    "attachment" => fopen($filePath, 'r'),
                    "noticeJson" => strval(json_encode($notices))
                );
                
                $ret = $this->enviaInformeNovo($informesNexti, $nomeInforme, $informe);
                unlink($filePath);
                sleep(2);
            }else{
                var_dump("Não foi encontrado Informe: " . $nomeInforme);
                $erro = "Não foi encontrado Informe: " . $nomeInforme;
                $this->atualizaRegistroComErro($informe, 2, $erro);
                unlink($filePath);
                sleep(2);
            }
        }
        $this->info("Total de {$nInformes} informe(s) informesNexti!");
    }

    private function enviaInformeNovo($informesNexti, $pdfInforme, $informe) {
        $name = $pdfInforme;
        $this->info("Enviando o informe {$name} para API Nexti");

        $response = $this->restClient->postMultipartSimple("notices/attachment", $informesNexti)->getResponse();
        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o informe {$name} para API Nexti: {$errors}");
            $this->atualizaRegistroComErro($informe, 2, $errors);
        } else {
            $this->info("Informe {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $informeInserido = $responseData['value'];
            $id = $informeInserido['id'];

            $this->atualizaRegistro($informe, 1, $id);
        }
    }

    private function atualizaRegistro($informe, $situacao, $id) {
        $obs = date('d/m/Y H:i:s') . ' - Enviado com Sucesso';
        DB::connection('sqlsrv')->statement(
            "
                UPDATE ".env('DB_OWNER')."NEXTI_AVISOS_EPI 
                SET ".env('DB_OWNER')."NEXTI_AVISOS_EPI.SITUACAO = {$situacao}, 
                ".env('DB_OWNER')."NEXTI_AVISOS_EPI.OBSERVACAO = '{$obs}', 
                ".env('DB_OWNER')."NEXTI_AVISOS_EPI.ID = '{$id}' 
                WHERE ".env('DB_OWNER')."NEXTI_AVISOS_EPI.NUMEMP = {$informe->NUMEMP}
                AND ".env('DB_OWNER')."NEXTI_AVISOS_EPI.CODFIL = '{$informe->CODFIL}'
                AND ".env('DB_OWNER')."NEXTI_AVISOS_EPI.NUMCAD = '{$informe->NUMCAD}'
                AND ".env('DB_OWNER')."NEXTI_AVISOS_EPI.STARTDATE = CONVERT(DATETIME, '{$informe->STARTDATE}', 120)
            "
        );
    } 

    private function atualizaRegistroComErro($informe, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement(
            "
                UPDATE ".env('DB_OWNER')."NEXTI_AVISOS_EPI 
                SET ".env('DB_OWNER')."NEXTI_AVISOS_EPI.SITUACAO = {$situacao}, 
                ".env('DB_OWNER')."NEXTI_AVISOS_EPI.OBSERVACAO = '{$obs}' 
                WHERE ".env('DB_OWNER')."NEXTI_AVISOS_EPI.NUMEMP = {$informe->NUMEMP}
                AND ".env('DB_OWNER')."NEXTI_AVISOS_EPI.CODFIL = '{$informe->CODFIL}'
                AND ".env('DB_OWNER')."NEXTI_AVISOS_EPI.NUMCAD = '{$informe->NUMCAD}'
                AND ".env('DB_OWNER')."NEXTI_AVISOS_EPI.STARTDATE = CONVERT(DATETIME, '{$informe->STARTDATE}', 120)
            "
        );
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

        $this->enviaInformesNovos();
    }
}