<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaTrocaSindicatos extends Command
{
    protected $signature = 'BuscaTrocaSindicatos';

    protected $description = 'Command description';

    private $restClient = null;

    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');
        parent::__construct();
        $this->query = "";
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

    private function getUltimaDataTrocaSindicato()
    {
        $dataRetro = date("Y-m-d", strtotime("-1 days"));
        $data = DateTime::createFromFormat('Y-m-d', $dataRetro);
        //var_dump($data);exit;
        return $data;
    }

    private function buscaTrocaTrocaSindicato()
    {
        $data = $this->getUltimaDataTrocaSindicato();

        do {    
            $start = DateTime::createFromFormat('dmYHis', $data->format('dmYHis'));
            $finish = DateTime::createFromFormat('dmYHis', $start->format('dmYHis'));
            $finish->modify('+1 day');

            $start_str = $start->format('dmY000000');
            $finish_str = $finish->format('dmY') . '000000';
            // $start_str  = '16082020212220';
            // $finish_str = '01122099000000';

            $this->info("Buscando sindicatos...{$start_str} - {$finish_str}");
            $this->buscarTrocaSindicato(0, $start_str, $finish_str);
            $start->format('Y-m-d H:i:s');
            $start->modify("+1 day");
            $data = $start;
        } while (strtotime($start->format('Y-m-d')) <= strtotime(date('Y-m-d')));
    }

    public function buscarTrocaSindicato($page = 0, $start, $finish)
    {
        // $response = $this->restClient->get("tradeunionstransfers/person/{$itemColaborador->id}/start/{$ultimaData}/finish/{$dataLimite}", [
        //     'page' => 0,
        //     'size' => 1000
        // ])->getResponse();

        $response = $this->restClient->get("/tradeunionstransfers/lastupdate/start/{$start}/finish/{$finish}", [
            'page' => $page,
            'size' => 1000
        ])->getResponse();

        $responseData = $this->restClient->getResponseData();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar troca de sindicatos: {$errors}");
        } else {
            if ($responseData) {
                $content = $responseData['content'];

                if (sizeof($content) > 0) {
                    foreach ($content as $reg) {
                        $reg['personExternalId'] = $reg['personExternalId']??0;
                        $reg['transferDate'] = DateTime::createFromFormat('dmYHis', $reg['transferDate'])->format('Y-m-d H:i:s');
                        $reg['lastUpdate'] = DateTime::createFromFormat('dmYHis', $reg['lastUpdate'])->format('Y-m-d H:i:s');
                        //var_dump($reg);
                        $this->addTrocaSindicato($reg);
                        //usleep(10000);
                    }
                    $totalPages = $responseData['totalPages'];
                    if ($page < $totalPages) {
                        $this->buscarTrocaSindicato($page + 1, $start, $finish);
                    }
                }
            }
            else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }
   
    private function addTrocaSindicato($troca) {
        $ausenciaExistente = $this->getTrocaSindicato($troca['id']);

        $query = "SELECT 
                    COUNT(ID) AS quantidade 
                FROM ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS 
                WHERE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.ID = {$troca['id']}";

        $retorno = DB::connection('sqlsrv')->select($query);

        $troca['transferDate'] = explode(" ",$troca['transferDate']);
        $troca['transferDate'] = $troca['transferDate'][0] . " 00:00:00";

        if($ausenciaExistente) {

            $troca['personName'] = str_replace("'", '', $troca['personName']);
            $tipo = isset($troca['removed']) && $troca['removed'] == true ? 3 : 0;

            $sql = 
            "UPDATE 
            ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS 
            SET ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.PERSONEXTERNALID = '{$troca['personExternalId']}',
            ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.PERSONID = '{$troca['personId']}',
            ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.PERSONNAME = '{$troca['personName']}',
            ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.TRADEUNIONEXTERNALID = '{$troca['tradeUnionExternalId']}',
            ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.TRADEUNIONID = '{$troca['tradeUnionId']}',
            ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.TRADEUNIONNAME = '{$troca['tradeUnionName']}',
            ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.TRANSFERDATE = '{$troca['transferDate']}',
            ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.TIPO = {$tipo}
            WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.ID = {$troca['id']}";

            
            $this->info("Troca {$troca['id']} atualizada com sucesso!");
        } else {
            if (isset($retorno[0]->quantidade) && $retorno[0]->quantidade > 0) {
                $sit = 0;
            } 
            else {
                $sit = 0;
            }

            $troca['personName'] = str_replace("'", "", $troca['personName']);
            $tipo = isset($troca['removed']) && $troca['removed'] == true ? 3 : 0;

            $sql = 
                "INSERT INTO ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS 
                (ID, 
                LASTUPDATE,
                PERSONEXTERNALID, 
                PERSONID, 
                PERSONNAME, 
                TRADEUNIONEXTERNALID, 
                TRADEUNIONID, 
                TRADEUNIONNAME, 
                TRANSFERDATE, 
                TIPO, 
                SITUACAO, 
                OBSERVACAO) 
                VALUES 
                (
                '{$troca['id']}', 
                '{$troca['lastUpdate']}',
                '{$troca['personExternalId']}', 
                {$troca['personId']}, 
                '{$troca['personName']}', 
                {$troca['tradeUnionExternalId']}, 
                '{$troca['tradeUnionId']}', 
                '{$troca['tradeUnionName']}', 
                '{$troca['transferDate']}', 
                {$tipo}, 
                '{$sit}', 
                '')";
            
            $this->info("Troca {$troca['id']} foi inserida com sucesso.");
        }

        DB::connection('sqlsrv')->statement($sql);
    }

    private function getTrocaSindicato($id) {
        $sql = "SELECT * FROM ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS WHERE ID = {$id}";
        return DB::connection('sqlsrv')->select($sql);
    }

    private function insertLogs($msg, $dataInicio, $dataFim)
    {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime,'{$dataInicio}',120), convert(datetime,'{$dataFim}',120), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }

    public function removeTodos() 
    {
        $sql = "SELECT * FROM ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS";
        $retorno = DB::connection('sqlsrv')->select($sql);
        foreach ($retorno as $item) {
            $response = $this->restClient->delete("tradeunionstransfers/{$item->ID}", [], [])->getResponse();
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

        $ret = $this->buscaTrocaTrocaSindicato();
    }
}
