<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class CriarAvisosFerias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CriarAvisosFerias';

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
                fe.CNPJ AS CNPJ_EMPRESA,
                fu.CNPJ AS CNPJ_UNIDADE,
                fa.*
            FROM ".env('DB_OWNER')."FLEX_AVISOS fa
            JOIN ".env('DB_OWNER')."FLEX_EMPRESA fe
                ON fe.NUMEMP = fa.NUMEMP
            LEFT JOIN (
                SELECT CODIGO_FILIAL, CNPJ, 
                       ROW_NUMBER() OVER (PARTITION BY CODIGO_FILIAL ORDER BY CODIGO_FILIAL) AS rn
                FROM ".env('DB_OWNER')."FLEX_UNIDADE
            ) fu
                ON fu.CODIGO_FILIAL = fa.CODFIL AND fu.rn = 1
            WHERE fa.NOME = 'Aviso de Férias'
            AND (fa.ARQUIVO = '0' OR fa.ARQUIVO = '');
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function buscaDocumentoAviso($pendente){
        $this->info("Buscando Aviso Colaborador {$pendente->NUMCAD}.");

        $senhaEncode = base64_encode(env('LoginWebService') . ':' . env('SenhaWebService'));

        $pendente->CNPJ_UNIDADE = str_replace('-','',$pendente->CNPJ_UNIDADE);
        $pendente->CNPJ_UNIDADE = str_replace('/','',$pendente->CNPJ_UNIDADE);
        $pendente->CNPJ_UNIDADE = str_replace('.','',$pendente->CNPJ_UNIDADE);

        $startDate = date("d/m/Y", strtotime($pendente->STARTDATE));
        $finishDate = date("d/m/Y", strtotime($pendente->FINISHDATE));

        $array = [
            "empresa" => $pendente->CNPJ_UNIDADE,
            "codEmpresa" =>"",
            "codFilial" => "",
            "periodo_inicial" => $startDate,
            "periodo_final" => $finishDate,
            "CPF"=> $pendente->CPF,
            "matricula_inicial" => '',
            "matricula_final" => '',
            "pagamento_inicial" => date("d/m/Y", strtotime('1900-01-01')),
            "pagamento_final" => date("d/m/Y", strtotime('2100-01-01')),
            "tipo_rel" => 'AVISO'
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('REST_TOTVS') . '/WebReciboFerias/',
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

        $error = json_decode($response);

        if (isset($error->code) && $error->code == 500) {
            var_dump($error->message);
            return;
        }

        if(empty($response)){
            var_dump('vazio');
            return;
        }

        curl_close($curl);

        return $response;
    }

    private function criarAvisos(){
        $pendentes = $this->buscaAvisosPendetes();

        if(!$pendentes){
            $this->info("Não existem avisos a serem gerados.");
            return;
        }

        foreach($pendentes as $pendente){
            $documento = $this->buscaDocumentoAviso($pendente);
            if($this->is_json($documento)) {
                $response = json_decode($documento);
                $this->error($response->errorMessage ?? 'Erro ao consultar o documento');
                continue;
            }

            if($documento == null){
                continue;
            }
            
            $this->atualizarDocumento($pendente, $documento);
        }
    }

    private function atualizarDocumento($pendente, $documento){
        $documento = base64_encode($documento);

        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_AVISOS
            SET ".env('DB_OWNER')."FLEX_AVISOS.ARQUIVO = '{$documento}'
            WHERE ".env('DB_OWNER')."FLEX_AVISOS.NUMEMP = {$pendente->NUMEMP}
            AND ".env('DB_OWNER')."FLEX_AVISOS.CODFIL = '{$pendente->CODFIL}'
            AND ".env('DB_OWNER')."FLEX_AVISOS.NUMCAD = '{$pendente->NUMCAD}'
            AND ".env('DB_OWNER')."FLEX_AVISOS.CPF = '{$pendente->CPF}'
            AND ".env('DB_OWNER')."FLEX_AVISOS.IDCOLABORADOR = '{$pendente->IDCOLABORADOR}'
            AND ".env('DB_OWNER')."FLEX_AVISOS.STARTDATE = CONVERT(DATETIME, '{$pendente->STARTDATE}', 120)
            AND ".env('DB_OWNER')."FLEX_AVISOS.FINISHDATE = CONVERT(DATETIME, '{$pendente->FINISHDATE}', 120)
            AND ".env('DB_OWNER')."FLEX_AVISOS.DATAPGTO =  CONVERT(DATETIME, '{$pendente->DATAPGTO}', 120)
            AND ".env('DB_OWNER')."FLEX_AVISOS.NOME = '{$pendente->NOME}'
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