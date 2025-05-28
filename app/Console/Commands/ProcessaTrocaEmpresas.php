<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaTrocaEmpresas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTrocaEmpresas';

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
            ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.*,
            COLAB_NOVO.NOMFUN AS NAME,
            COLAB_NOVO.DATADMISSAO AS DATADM,
            COLAB_OLD.IDEXTERNO AS IDEXTERNOANTIGO,
            COLAB_NOVO.IDEXTERNO AS IDEXTERNONOVO,
            ".env('DB_OWNER')."FLEX_EMPRESA.IDEXTERNO AS EMP_NOVA
            FROM ".env('DB_OWNER')."FLEX_TROCA_EMPRESA
            JOIN ".env('DB_OWNER')."FLEX_EMPRESA
                ON ".env('DB_OWNER')."FLEX_EMPRESA.NUMEMP = ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.EMPATU
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR COLAB_OLD
                ON COLAB_OLD.IDEXTERNO = ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.IDEXTERNO_ANTIGO
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR COLAB_NOVO
                ON COLAB_NOVO.IDEXTERNO = ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.IDEXTERNO_NOVO
            WHERE ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.SITUACAO IN (0,2)
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

    private function enviaTrocaEmpresaNova($troca, $infos) {
        $name = $troca['name'];
        $this->info("Enviando o Troca {$name} para API Nexti");
        $newEmp = trim($infos->EMP_NOVA);

        $response = $this->restClient->post("/persons/migrateperson/{$infos->IDEXTERNOANTIGO}/{$troca['externalId']}/{$newEmp}/{$infos->CADATU}", ['timeout' => 60], [
            'json' => $troca
        ])->getResponse();;

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o troca {$name} para API Nexti: {$errors}");
            $this->atualizaRegistroComErro($infos, 2, $errors);
            
        } else {
            $this->info("Troca {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            //var_dump($responseData);die();
            $trocaEmpInserido = $responseData['value'];
            $id = $trocaEmpInserido['id'];


            $this->atualizaRegistro($infos, 1, $id);
        }
    }

    private function enviaTrocaEmpresa() {
        $this->info("Iniciando envio de dados:");
        $trocas = DB::connection('sqlsrv')->select($this->query);
        $nTrocas = sizeof($trocas);

        foreach ($trocas as $troca) {
            $trocaNexti = [
                'personSituationId'     => 1,
                'personTypeId'          => 1,
                'name'                  => $troca->NAME,
                'admissionDate'         => $troca->DATADM . '000000',
                'allowDevicePassword'   => false,
                'allowMobileClocking'   => false,
                'externalId'            => $troca->IDEXTERNONOVO,
            ];

            $ret = $this->enviaTrocaEmpresaNova($trocaNexti, $troca);
            sleep(2);
        }
        $this->info("Total de {$nTrocas} trocas(s) cadastradas!");
    }

    private function atualizaRegistro($infos, $situacao, $id) {
        $obs = date('d/m/Y H:i:s') . ' - Colaborador migrado com sucesso.';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_EMPRESA 
            SET ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.NUMEMP = {$infos->NUMEMP}
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.NUMCAD = {$infos->NUMCAD}
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.CADATU = {$infos->CADATU}
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.EMPATU = {$infos->EMPATU}
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.IDEXTERNO_NOVO = {$infos->IDEXTERNO_NOVO}
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.IDEXTERNO_ANTIGO = {$infos->IDEXTERNO_ANTIGO}
        ");
    } 

    private function atualizaRegistroComErro($infos, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_EMPRESA 
            SET ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.NUMEMP = {$infos->NUMEMP}
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.NUMCAD = {$infos->NUMCAD}
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.CADATU = {$infos->CADATU}
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.EMPATU = {$infos->EMPATU}
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.IDEXTERNO_NOVO = {$infos->IDEXTERNO_NOVO}
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.IDEXTERNO_ANTIGO = {$infos->IDEXTERNO_ANTIGO}
        ");
    } 

    public function atualizarColab()
    {
        $query = 
        "  
            SELECT * FROM ".env('DB_OWNER')."FLEX_TROCA_EMPRESA 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.SITUACAO = 1 
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.ID > 0 
            AND EXISTS (
                SELECT * FROM ".env('DB_OWNER')."FLEX_COLABORADOR
                WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO = ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.IDEXTERNO_NOVO
                AND ".env('DB_OWNER')."FLEX_COLABORADOR.ID = 0
            )
        ";

        $retorno = DB::connection('sqlsrv')->select($query);

        foreach ($retorno as $item) {
            $query = 
            "
                UPDATE 
                ".env('DB_OWNER')."FLEX_COLABORADOR 
                SET TIPO = 1, SITUACAO = 0, ID = {$item->ID}
                WHERE NUMEMP = {$item->EMPATU}
                  AND TIPCOL = {$item->TIPCOL}
                  AND NUMCAD = {$item->CADATU} 
            ";
            DB::connection('sqlsrv')->statement($query);
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

        $this->enviaTrocaEmpresa();
        $this->atualizarColab();
    }
}
