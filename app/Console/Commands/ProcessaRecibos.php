<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class ProcessaRecibos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaRecibos';

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
                ".env('DB_OWNER')."FLEX_AVISOS.*
            FROM ".env('DB_OWNER')."FLEX_AVISOS
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
            WHERE ".env('DB_OWNER')."FLEX_AVISOS.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_AVISOS.NOME = 'Recibo de Férias'
            AND ".env('DB_OWNER')."FLEX_AVISOS.ARQUIVO <> '0'
            AND ".env('DB_OWNER')."FLEX_AVISOS.SITUACAO IN (0,2)
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
            $iniAqui = date("d/m/Y", strtotime($informe->STARTDATE_AQUI));
            $fimAqui = date("d/m/Y", strtotime($informe->FINISHDATE_AQUI));
            $iniGozo = date("d/m/Y", strtotime($informe->STARTDATE));
            $fimGozo = date("d/m/Y", strtotime($informe->FINISHDATE));
            $retornoFerias = date("d/m/Y", strtotime("+1 days", strtotime($informe->FINISHDATE)));

            $mensagem = 
            "Segue em anexo o seu recibo de férias.";

            $notices = array(
                "finishDate"            => date("dmY", strtotime("+90days")).'235959',
                "name"                  => $informe->NOME . ' - ' . $informe->NOMFUN,
                "noticeCollectorId"     => 3,
                "noticeShowId"          => 3,
                "noticeStatusId"        => 1,
                "noticeTypeId"          => 2,
                "persons"               => [intval($informe->IDCOLABORADOR)], 
                "startDate"             => date("dmY") . '000001',
                "text"                  => $mensagem,
            );

            $nomeInforme = 'Recibo' . $informe->CPF;

            $pdfBase64 = $informe->ARQUIVO;
            $pdfContent = base64_decode($pdfBase64);

            $filePath = "C:\\laragon\\www\\maxipark\\tempAvisos\\{$nomeInforme}.pdf";
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
                var_dump("Não foi encontrado recibo: " . $nomeInforme);
                $erro = "Não foi encontrado recibo: " . $nomeInforme;
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
                UPDATE ".env('DB_OWNER')."FLEX_AVISOS 
                SET ".env('DB_OWNER')."FLEX_AVISOS.SITUACAO = {$situacao}, 
                ".env('DB_OWNER')."FLEX_AVISOS.OBSERVACAO = '{$obs}', 
                ".env('DB_OWNER')."FLEX_AVISOS.ID = '{$id}' 
                WHERE ".env('DB_OWNER')."FLEX_AVISOS.NUMEMP = {$informe->NUMEMP}
                AND ".env('DB_OWNER')."FLEX_AVISOS.CODFIL = '{$informe->CODFIL}'
                AND ".env('DB_OWNER')."FLEX_AVISOS.NUMCAD = '{$informe->NUMCAD}'
                AND ".env('DB_OWNER')."FLEX_AVISOS.CPF = '{$informe->CPF}'
                AND ".env('DB_OWNER')."FLEX_AVISOS.STARTDATE = CONVERT(DATETIME, '{$informe->STARTDATE}', 120)
                AND ".env('DB_OWNER')."FLEX_AVISOS.FINISHDATE = CONVERT(DATETIME, '{$informe->FINISHDATE}', 120)
                AND ".env('DB_OWNER')."FLEX_AVISOS.NOME = 'Recibo de Férias'
            "
        );
    } 

    private function atualizaRegistroComErro($informe, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement(
            "
                UPDATE ".env('DB_OWNER')."FLEX_AVISOS 
                SET ".env('DB_OWNER')."FLEX_AVISOS.SITUACAO = {$situacao}, 
                ".env('DB_OWNER')."FLEX_AVISOS.OBSERVACAO = '{$obs}' 
                WHERE ".env('DB_OWNER')."FLEX_AVISOS.NUMEMP = {$informe->NUMEMP}
                AND ".env('DB_OWNER')."FLEX_AVISOS.CODFIL = '{$informe->CODFIL}'
                AND ".env('DB_OWNER')."FLEX_AVISOS.NUMCAD = '{$informe->NUMCAD}'
                AND ".env('DB_OWNER')."FLEX_AVISOS.CPF = '{$informe->CPF}'
                AND ".env('DB_OWNER')."FLEX_AVISOS.STARTDATE = CONVERT(DATETIME, '{$informe->STARTDATE}', 120)
                AND ".env('DB_OWNER')."FLEX_AVISOS.FINISHDATE = CONVERT(DATETIME, '{$informe->FINISHDATE}', 120)
                AND ".env('DB_OWNER')."FLEX_AVISOS.NOME = 'Recibo de Férias'
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
        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->enviaInformesNovos();
    }
}