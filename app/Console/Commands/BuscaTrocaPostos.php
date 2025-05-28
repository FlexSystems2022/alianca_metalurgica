<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaTrocaPostos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaTrocaPostos';

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
        $this->query = "";
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

    private function getUltimaDataTrocaPostos()
    {
        $sql = "SELECT MAX(LASTUPDATE) as LASTUPDATE FROM ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS";
        $ultimoRegistro = DB::connection('sqlsrv')->selectOne($sql);

        $data = DateTime::createFromFormat('Y-m-d H:i:s', '2025-01-01 00:00:00');
        if ($ultimoRegistro && $ultimoRegistro->LASTUPDATE != null) {
            $data = $ultimoRegistro->LASTUPDATE;
            $data = DateTime::createFromFormat('Y-m-d H:i:s', explode('.', $data)[0]);
            $data->modify("-1 second");
        }

        return $data;
    }

    private function getTrocaPosto($id)
    {
        $sql = "SELECT * FROM ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.ID = {$id}";
        return DB::connection('sqlsrv')->select($sql);
    }

    
    private function buscaTrocaPostos()
    {
        $data = $this->getUltimaDataTrocaPostos();

        do {    
            $start = DateTime::createFromFormat('dmYHis', $data->format('dmYHis'));
            $finish = DateTime::createFromFormat('dmYHis', $start->format('dmYHis'));
            $finish->modify('+1 day');

            $start_str = $start->format('dmY000000');
            $finish_str = $finish->format('dmY000000');
           
            $this->info("Buscando troca de postos.../start/{$start_str}/finish/{$finish_str}");
            $this->getTrocaPostoRecursivo(0, $start_str, $finish_str);


            $start->format('Y-m-d H:i:s');
            $start->modify("+1 day");
            $data = $start;
        } while (strtotime($start->format('Y-m-d')) <= strtotime(date('Y-m-d')));
    }

    private function getTrocaPostoRecursivo($page, $start, $finish)
    {
        $response = $this->restClient->get("workplacetransfers/lastupdate/start/{$start}/finish/{$finish}", [
            'page' => $page,
            'size' => 1000
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar troca de postos: {$errors}");
            return;
        }

        $responseData = $this->restClient->getResponseData();
        if (!$responseData) {
            $this->info("Não foi possível ler a resposta da consulta.");
            return;
        }

        $content = $responseData['content'];
        if ($page == 0) {
            $this->info("Armazendo {$responseData['totalElements']} registros...");
        }

        $this->info("API Nexti retornou os resultados: Pagina {$page}.");
        if (sizeof($content) > 0) {
            foreach ($content as $reg) {
                $troca = $this->getTrocaPosto($reg['id']);
                //print_r($troca);

                if (!isset($reg['personExternalId'])) {
                    $reg['personExternalId'] = 0;
                }

                $transferDateTime = $reg['transferDateTime'];
                $transferDateTime = DateTime::createFromFormat('dmYHis', $transferDateTime)->format('Y-m-d H:i:s A');
                $reg['transferDateTime'] = $transferDateTime;

                $lastUpdate = $reg['lastUpdate'];
                $lastUpdate = DateTime::createFromFormat('dmYHis', $lastUpdate)->format('Y-m-d H:i:s A');
                $reg['lastUpdate'] = $lastUpdate;

                $reg['removed'] = isset($reg['removed']) && $reg['removed'] == true ? 1 : 0;

                $this->addTrocaPosto($reg);
                usleep(10000);
            }

            // Chamada recursiva para carregar proxima pagina
            $totalPages = $responseData['totalPages'];
            if ($page < $totalPages) {
                $this->getTrocaPostoRecursivo($page + 1, $start, $finish);
            }
        }
    }

    private function addTrocaPosto($troca)
    {
        $trocaExistente = $this->getTrocaPosto($troca['id']);

        if (!empty($trocaExistente) && $trocaExistente[0]->REMOVED == 1 && $trocaExistente[0]->SITUACAO == 1) {
            return;
        }

        $tipo = $troca['removed'] == 1 ? 3 : 0;
        $troca['lastUpdate'] = $this->formatDate($troca['lastUpdate']);
        $troca['workplaceExternalId'] = $troca['workplaceExternalId'] ?? '';
        $obs = date("d/m/Y H:i:s") . ' - ' . ($trocaExistente ? 'Atualizado' : 'Cadastrado') . ' por BuscaTrocaPostos(addTrocaPosto)';

        if ($trocaExistente) {
            $sql = "
                UPDATE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS
                SET LASTUPDATE = CONVERT(DATETIME, '{$troca['lastUpdate']}', 120),
                    PERSONEXTERNALID = '{$troca['personExternalId']}',
                    PERSONID = {$troca['personId']},
                    TRANSFERDATETIME = CONVERT(DATETIME, '{$troca['transferDateTime']}', 120),
                    WORKPLACEEXTERNALID = '{$troca['workplaceExternalId']}',
                    WORKPLACEID = {$troca['workplaceId']},
                    REMOVED = '{$troca['removed']}',
                    TIPO = {$tipo},
                    USERREGISTERID = {$troca['userRegisterId']},
                    INTEGRA_PROTHEUS = 0,
                    SITUACAO = 0,
                    OBSERVACAO = '{$obs}'
                WHERE ID = {$troca['id']}
            ";
            $this->info("Troca de posto {$troca['id']} atualizada com sucesso!");
        } else {
            $sql = "
                INSERT INTO ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS
                (ID, LASTUPDATE, PERSONEXTERNALID, PERSONID, TRANSFERDATETIME, WORKPLACEEXTERNALID,
                 WORKPLACEID, REMOVED, TIPO, SITUACAO, USERREGISTERID, INTEGRA_PROTHEUS, OBSERVACAO)
                VALUES
                ({$troca['id']}, CONVERT(DATETIME, '{$troca['lastUpdate']}', 120),
                 '{$troca['personExternalId']}', {$troca['personId']}, CONVERT(DATETIME, '{$troca['transferDateTime']}', 120),
                 '{$troca['workplaceExternalId']}', {$troca['workplaceId']}, '{$troca['removed']}',
                 {$tipo}, 0, {$troca['userRegisterId']}, 0, '{$obs}')
            ";
            $this->info("Troca de posto {$troca['id']} foi inserida com sucesso.");
        }

        DB::connection('sqlsrv')->statement($sql);
    }

    private function formatDate($date)
    {
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        if ($dateTime) {
            return $dateTime->format('Y-m-d H:i:s');
        }
        return $date;
    }

    private function insertLogs($msg, $dataInicio, $dataFim)
    {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime,'{$dataInicio}',120), convert(datetime,'{$dataFim}',120), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
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

        $this->buscaTrocaPostos();
    }
}
