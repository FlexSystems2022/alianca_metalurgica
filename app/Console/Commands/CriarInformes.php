<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;

class CriarInformes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CriarInformes';

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

    private function buscaAvisosPendetes(){
        $query = 
        "
            SELECT 
            ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.*,
            ".env('DB_OWNER')."FLEX_EMPRESA.CNPJ AS CNPJ_EMPRESA
            FROM ".env('DB_OWNER')."FLEX_AVISOS_FERIAS
            JOIN ".env('DB_OWNER')."FLEX_EMPRESA
                ON ".env('DB_OWNER')."FLEX_EMPRESA.NUMEMP = ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.NUMEMP
                AND ".env('DB_OWNER')."FLEX_EMPRESA.CODFIL = ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.CODFIL
            WHERE ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.NOME = 'Informes'
            AND (
                ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.ARQUIVO = '0'
                OR ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.ARQUIVO = ''
            )
            ORDER BY ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.NUMCAD
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function buscaDocumentoAviso($pendente){
        $this->info("Buscando Informe Colaborador {$pendente->NOMFUN}.");

        $senhaEncode = base64_encode(env('LoginWebService') . ':' . env('SenhaWebService'));

        $pendente->CNPJ_EMPRESA = str_replace('-','',$pendente->CNPJ_EMPRESA);
        $pendente->CNPJ_EMPRESA = str_replace('/','',$pendente->CNPJ_EMPRESA);
        $pendente->CNPJ_EMPRESA = str_replace('.','',$pendente->CNPJ_EMPRESA);

        $ano = date('Y') - 1;

        $array = [
            "empresa" => $pendente->CNPJ_EMPRESA,
            "codEmpresa" =>"",
            "codFilial" => "",
            "ano" => "{$ano}",
            "CPF"=> $pendente->CPF,
            "matricula_inicial" => "",
            "matricula_final" => ""
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('REST_TOTVS') . '/WebConsultaDIRF/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS =>json_encode($array),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . $senhaEncode
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $error = json_decode($response);

        if (isset($error->code) && $error->code == 500) {
            var_dump($error->message);
            return;
        }

        if(empty($response)){
            var_dump('vazio');
            return;
        }

        // Converte o XML em PDF usando Dompdf
        $dompdf = new Dompdf();
        $dompdf->loadHtml($response);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();

        return $output;
    }

    private function criarAvisos(){
        $pendentes = $this->buscaAvisosPendetes();

        if(!$pendentes){
            $this->info("Não existem avisos a serem gerados.");
            return;
        }

        foreach($pendentes as $pendente){
            $documento = $this->buscaDocumentoAviso($pendente);
            $this->atualizarDocumento($pendente, $documento);
        }
    }

    private function atualizarDocumento($pendente, $documento){
        $documento = base64_encode($documento);

        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_AVISOS_FERIAS
            SET ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.ARQUIVO = '{$documento}'
            WHERE ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.NUMEMP = {$pendente->NUMEMP}
            AND ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.CODFIL = '{$pendente->CODFIL}'
            AND ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.NUMCAD = '{$pendente->NUMCAD}'
            AND ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.CPF = '{$pendente->CPF}'
            AND ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.STARTDATE = CONVERT(DATETIME, '{$pendente->STARTDATE}', 120)
            AND ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.FINISHDATE = CONVERT(DATETIME, '{$pendente->FINISHDATE}', 120)
            AND ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.DATAPGTO =  CONVERT(DATETIME, '{$pendente->DATAPGTO}', 120)
            AND ".env('DB_OWNER')."FLEX_AVISOS_FERIAS.NOME = '{$pendente->NOME}'
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
        $this->criarAvisos();
    }

    private function is_json(?string $str=null): bool
    {
        try {
            $str = trim($str) ?? null;
            if(!$str) {
                return false;
            }

            $json = json_decode($str);

            return $json && $str != $json;
        } catch(\Throwable $e) {
            return false;
        }
    }
}