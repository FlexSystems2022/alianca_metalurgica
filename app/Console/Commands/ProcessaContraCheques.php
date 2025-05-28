<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaContraCheques extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaContraCheques';

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
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE.*,
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.IDEXTERNO as EXTERNOCPT,
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.ID as IDCPT,
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.NAME as NAMECPT,
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.TIPO as TIPOCPT,
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.SITUACAO as SITCPT,
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.OBSERVACAO as OBSCPT
            FROM ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE
            JOIN ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA 
            ON ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.IDEXTERNO = ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE.CONTRA_CHEQUE_CMP 
            AND ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_COMPETENCIA.SITUACAO = 1
        ";
    }

    // NOVOS
    public function getContraChequeNovo()
    {
        $where = 
        "  
            WHERE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE.SITUACAO IN(0,2)
        "
        ;
        $holerite = DB::connection('sqlsrv')->select($this->query . $where);
        
        return $holerite;
    }

    public function getContraChequeExcluir()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE.TIPO = 3 
            AND ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE.SITUACAO IN(0,2)
        ";
        $holerite = DB::connection('sqlsrv')->select($this->query . $where);
        
        return $holerite;
    }


    private function processaContraChequesNovos()
    {
        $this->info("Iniciando envio de dados:");
        $contra = $this->getContraChequeNovo();
        $nContra = sizeof($contra);

        // var_dump($contra);exit;
        foreach ($contra as $itemContra) {
            $idEmpresa = $itemContra->companyExternalId;
            $idExterno = $itemContra->companyId;

            if ($idEmpresa) {

                $query = "select
                ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.COST,
                ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.DESCRIPTION,
                ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.ID,
                ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.PAYCHECKRECORDTYPEID,
                ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.REFERENCE,
                ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.ID_CONTRA_CHEQUE,
                ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.TIPO,
                ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.SITUACAO,
                ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.OBSERVACAO
                from ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS 
                WHERE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.ID_CONTRA_CHEQUE = '" . $itemContra->IDEXTERNO . "' 
                ORDER BY ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.PAYCHECKRECORDTYPEID, ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS.ID";

                $eventosContra = DB::connection('sqlsrv')->select($query);
                $arrEventos = [];
                $totTipoUm = 0;
                $totTipoDois = 0;
                if ($eventosContra) {

                    foreach ($eventosContra as $itemEvento) {
                        if ($itemEvento->PAYCHECKRECORDTYPEID == 1) {
                            $tipoEvento = 1;
                            $totTipoUm += $itemEvento->COST;
                        } else {
                            $tipoEvento = 2;
                            $totTipoDois += $itemEvento->COST;
                        }


                        $arrEventos[] = [
                            "cost"                 => floatval(number_format($itemEvento->COST,2,'.','')),
                            "description"          => $itemEvento->DESCRIPTION,
                            "externalId"           => $itemEvento->ID,
                            "paycheckRecordTypeId" => $tipoEvento,
                            "reference"            => round($itemEvento->REFERENCE, 2)
                        ];
                    }
                }
                //dd($itemContra->IDEXTERNO);
                $contraNexti = [
                    'baseFgts'                  => floatval(number_format($itemContra->baseFgts,2,'.','')),
                    'baseInss'                  => floatval(number_format($itemContra->baseInss,2,'.','')),
                    'baseIrrf'                  => floatval(number_format($itemContra->baseIrrf,2,'.','')),
                    'companyExternalId'         => $idEmpresa,
                    'companyId'                 => intval($idExterno),
                    'companyNumber'             => $itemContra->companyNumber,
                    'grossPay'                  => floatval(number_format($itemContra->grossPay,2,'.','')),
                    'monthFgts'                 => $itemContra->monthFgts,
                    'netPay'                    => floatval(number_format(($totTipoUm - $totTipoDois),2,'.','')),
                    "paycheckRecords"           => $arrEventos,
                    'personEnrolment'           => $itemContra->personEnrolment,
                    'personExternalId'          => $itemContra->personExternalId,
                    //'personId'                  => intval($itemContra->PERSONID),
                    'totalExpense'              => floatval(number_format($totTipoDois,2,'.','')),
                    'totalRevenue'              => floatval(number_format($totTipoUm,2,'.','')),
                    'externalPaycheckPeriodId'  => $itemContra->CONTRA_CHEQUE_CMP,
                    'paycheckPeriodId'          => $itemContra->IDCPT,
                    'paycheckPeriodName'        => $itemContra->NAMECPT, 
                    'externalId'                => $itemContra->IDEXTERNO, 
                ];

                //var_dump($contraNexti);exit;

                $ret = $this->enviaContraChequeNovo($contraNexti);
                sleep(1);
            }
        }

        $this->info("Total de {$nContra} contracheques(s) cadastrados!");
    }

    private function enviaContraChequeNovo($item)
    {
        $jsonItem = json_encode($item);

        //$paycheckperiodname = str_replace("/", "-", $item['paycheckPeriodName']);
        $this->info("Enviando o contracheque {$item['personExternalId']} para API Nexti");

        $response = $this->restClient->post('paychecks', [], [
            'json' => $item
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o contracheque {$item['personExternalId']} para API Nexti: {$errors}");
            $this->atualizaRegistroComErro($item['externalId'], 2, $errors);
        } else {
            $this->info("Contracheque {$item['personExternalId']} enviado com sucesso para API Nexti");
            $responseData = $this->restClient->getResponseData();
            $id = isset($responseData['value']['id']) ? $responseData['value']['id'] : (isset($responseData['id']) ? $responseData['id'] : false);

            if (!$id) {
                $this->info("Problema ao enviar o contracheque {$item['personExternalId']}. SEM ID");
                
            } else {
                $this->atualizaRegistro($item['externalId'], 1, $id);
                $this->atualizaEventosContracheque($item['externalId']);
            }
        }
    }

    public function atualizaEventosContracheque($idExternoContra){
        DB::connection('sqlsrv')->statement("UPDATE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS SET SITUACAO = 1 WHERE ID_CONTRA_CHEQUE = '{$idExternoContra}'");
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

    private function atualizaRegistro($idexterno, $situacao, $id)
    {
        $obs = 'Enviado dia: ' . date('d/m/Y H:i:s');
        DB::connection('sqlsrv')->statement("UPDATE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE SET SITUACAO = {$situacao}, OBSERVACAO = '{$obs}', ID = '{$id}' WHERE IDEXTERNO = '{$idexterno}'");
    }

    private function atualizaRegistroComErro($idexterno, $situacao, $obs = '')
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("UPDATE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE SET SITUACAO = {$situacao}, OBSERVACAO = '{$obs}' WHERE IDEXTERNO = '{$idexterno}'");
    }

    public function excluirContraCheque()
    {
        $retorno = $this->getContraChequeExcluir();
        if (sizeof($retorno) > 0) {
            foreach ($retorno as $itemExcluir) {
                $response = $this->restClient->delete("/paychecks/externalid/{$itemExcluir->IDEXTERNO}", [], [])->getResponse();

                if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
                    $errors = $this->getResponseErrors();
                    $this->info("Problema ao excluir o contra cheque {$itemExcluir->IDEXTERNO} na API Nexti: {$errors}");
                    $this->atualizaRegistroComErro($itemExcluir->IDEXTERNO, 2, $errors);
                } else {
                    $this->info("Contra cheque {$itemExcluir->IDEXTERNO} excluido com sucesso na API Nexti");
                    $this->deleteContraChequeBD($itemExcluir->CodFil, $itemExcluir->NumEmp, $itemExcluir->IDEXTERNO);
                }
            }
        }
    }

    public function deleteContraChequeBD($codfil, $numemp, $idExterno)
    {
        $query = "SELECT * FROM ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE WHERE IDEXTERNO = '{$idExterno}'";

        $itemExcluir = DB::connection('sqlsrv')->select($query);

        foreach ($itemExcluir as $itemExcluir) {
            $qr = "DELETE FROM ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE WHERE IDEXTERNO = '{$itemExcluir->IDEXTERNO}'";
            DB::connection('sqlsrv')->statement($qr);

            $qr = "DELETE FROM ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE_EVENTOS WHERE ID_CONTRA_CHEQUE = '{$itemExcluir->IDEXTERNO}'";
            DB::connection('sqlsrv')->statement($qr);
        }

        return true;
    }

    private function ajuste(){
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE
            SET 
            ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE.SITUACAO = 1
            WHERE ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE.TIPO = 0
            AND ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE.SITUACAO IN(0,2)
            AND ".env('DB_OWNER')."FLEX_CONTRA_CHEQUE.OBSERVACAO LIKE '%Já existe um registro salvo para o campo externalId com valor%'
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
        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->ajuste();
        $this->excluirContraCheque();
        $this->processaContraChequesNovos();
    }
}