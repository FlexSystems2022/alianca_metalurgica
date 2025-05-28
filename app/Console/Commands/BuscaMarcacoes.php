<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Shared\Provider\RestClient;
use DateTime;

class BuscaMarcacoes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaMarcacoes';

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

    private function getUltimaDataMarcacoes() {
        $sql = "SELECT MAX(LASTUPDATE) as LASTUPDATE FROM ".env('DB_OWNER')."NEXTI_RET_MARCACOES";
        $ultimoRegistro = DB::connection('sqlsrv')->select($sql);
        //$start = DateTime::createFromFormat('dmYHis', '10122020000000');
        if(sizeof($ultimoRegistro) > 0) {
            if($ultimoRegistro[0]->LASTUPDATE) {
                $start = $ultimoRegistro[0]->LASTUPDATE;
                $start = explode(".", $start)[0];
                //DateTime::createFromFormat('Y-m-d H:i:s', $start);
                $start = date('dmYHis', strtotime(explode('.', $start)[0]));
                $start = DateTime::createFromFormat('dmYHis', $start);
            }
        }else{
            $start = date('dmY000000', strtotime('-90 days'));
            //$start ='28072021000000';
            $start = DateTime::createFromFormat('dmYHis', $start);
            $start->format("dmYHis");
            //$start->modify("+1 second");
        }

        //$start = date('16092024000000');
        //$start ='28072021000000';
        // $start = DateTime::createFromFormat('dmYHis', $start);
        // $start->format("dmYHis");
        //$start->modify("+1 second");
       
        $start_str = $start;
        return ['start' => $start, 'start_str' => $start_str];
    }
    
    private function buscaTodasMarcacoes() {
        $data = $this->getUltimaDataMarcacoes();

        do {
            $start = $data['start'];
            $finish = DateTime::createFromFormat('dmYHis', $start->format('dmYHis'));
            //$finish->modify('+1 day');

            $start_str = $start->format('dmY000000');
            $finish_str = $finish->format('dmY235959');

            $start->modify("-3 hours");
           
            $this->info("Buscando marcacao...{$start_str} - {$finish_str}");
            $this->getMarcacaoRecursivo(0, $start_str, $finish_str);

            $start->modify("+1 day");
            $dataAtual = DateTime::createFromFormat('dmY', date("dmY"));

        } while($start <= $dataAtual);
    }

    private function getMarcacaoRecursivo($page, $start, $finish) {

        $query = \App\Helper\Query::build([
            'page' => $page,
            'size' => 100000,
            'types' => [1,2,3,4,5,6,7,8,9,10],
        ]);
        $contador = 0;
        
        $response = $this->restClient->get("clockings/lastupdate/start/{$start}/finish/{$finish}/types", [], [
            'query' => $query,
            'debug' => false,
        ])->getResponse();


        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar marcacao de postos: {$errors}");
            sleep(60);
            $this->info("Buscando marcacao...{$start} - {$finish} - {$page}");
            $this->getMarcacaoRecursivo($page, $start, $finish);
        } else {
            $responseData = $this->restClient->getResponseData();
            //print_r($responseData);
            if($responseData) {
                $content = $responseData['content'];
                $this->info("API Nexti retornou os resultados.");
                foreach ($content as $i => $reg) {
                    if ($reg['removed']) {
                        //$this->removeRegistro($reg['id']);
                    } else {
                        //$marcacao = $this->getMarcacao($reg['id']);
                        $clockingDate = $reg['clockingDate'];
                        $clockingDate = DateTime::createFromFormat('dmYHis', $clockingDate)->format('Y-m-d H:i:s');
                        $reg['clockingDate'] = $clockingDate;
                        $referenceDate = $reg['referenceDate'];
                        $referenceDate = DateTime::createFromFormat('dmYHis', $referenceDate)->format('Y-m-d H:i:s');
                        $reg['referenceDate'] = $referenceDate;
                        $registerDate = $reg['registerDate'];
                        $registerDate = DateTime::createFromFormat('dmYHis', $registerDate)->format('Y-m-d H:i:s');
                        $reg['registerDate'] = $registerDate;
                        $lastUpdate = $reg['lastUpdate'];
                        $lastUpdate = DateTime::createFromFormat('dmYHis', $lastUpdate)->format('Y-m-d H:i:s');
                        $reg['lastUpdate'] = $lastUpdate;

                        $diaAtual = DateTime::createFromFormat('dmYHis', date("dmYHis"))->format('Y-m-d H:i:s');
                        $reg['diaAtual'] = $diaAtual;

                        if(!array_key_exists('ip', $reg))
                            $reg['ip'] = '';
                        if(!array_key_exists('workplaceTimezone', $reg))
                            $reg['workplaceTimezone'] = '';
                        if(!array_key_exists('externalPersonId', $reg))
                            $reg['externalPersonId'] = '';
                        if(!array_key_exists('deviceCode', $reg))
                            $reg['deviceCode'] = '';
                        if(!array_key_exists('identificationMethodId', $reg))
                            $reg['identificationMethodId'] = 0;
                        if(!array_key_exists('directionTypeId', $reg))
                            $reg['directionTypeId'] = 0;
                        if(!array_key_exists('clockingCollectorId', $reg))
                            $reg['clockingCollectorId'] = 0;
                        $this->addMarcacao($reg);
                        //usleep(50000);
                        //$this->info("Marcacao {$reg['id']} salva com sucesso.");
                    }
                }

                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    sleep(60);
                    $this->getMarcacaoRecursivo($page + 1, $start, $finish);
                }
            }
            else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function addMarcacao($marcacao) {
        $marcacaoExistente = $this->getMarcacao($marcacao['id']);

        if($marcacaoExistente) {
            //$this->info("Marcação {$marcacao['id']} já existe!");

            $marcacao['clockingOriginId'] = isset($marcacao['clockingOriginId']) ? $marcacao['clockingOriginId'] : 0;
            $removed = isset($marcacao['removed']) && $marcacao['removed'] == true ? 1 : 0;

            $update = 
            "
                UPDATE ".env('DB_OWNER')."NEXTI_RET_MARCACOES 
                SET 
                CLOCKINGORIGINID = {$marcacao['clockingOriginId']},
                LASTUPDATE = CONVERT(DATETIME, '{$marcacao['lastUpdate']}', 120),
                REMOVED = {$removed}
                WHERE ID = {$marcacao['id']}
            ";
            DB::connection('sqlsrv')->statement($update);

            $this->info("Marcação {$marcacao['id']} foi atualizada com sucesso.");

            return;
        } else {
            $marcacao['latitude']   = isset($marcacao['latitude']) ? $marcacao['latitude'] : '-23.6320367';
            $marcacao['longitude']  = isset($marcacao['longitude']) ? $marcacao['longitude'] : '-46.6828331';

            $marcacao['externalWorkplaceId'] = isset($marcacao['externalWorkplaceId']) ? $marcacao['externalWorkplaceId'] : 0;
            $marcacao['clockingOriginId'] = isset($marcacao['clockingOriginId']) ? $marcacao['clockingOriginId'] : 0;
            $removed = isset($marcacao['removed']) && $marcacao['removed'] == true ? 1 : 0;


            $sql = 
            "
                INSERT INTO ".env('DB_OWNER')."NEXTI_RET_MARCACOES 
                    (ID, LASTUPDATE, PERSONID, WORKPLACEID, 
                    EXTERNALWORKPLACEID, EXTERNALPERSONID, REGISTERDATE, TICKETSIGNATURE, 
                    DIRECTIONTYPEID, CLOCKINGTYPENAME, DEVICECODE, TIMEZONE, 
                    WORKPLACETIMEZONE, CLOCKINGCOLLECTORNAME, IP, IDENTIFICATIONMETHODID, 
                    CLOCKINGCOLLECTORID, CLOCKINGTYPEID, CLOCKINGDATE, REFERENCEDATE, TIPO, SITUACAO, LATITUDE, LONGITUDE, CLOCKINGORIGINID, REMOVED)
                VALUES 
                    ({$marcacao['id']}, 
                    CONVERT(DATETIME, '{$marcacao['lastUpdate']}', 120),
                    {$marcacao['personId']}, 
                    {$marcacao['workplaceId']}, 
                    '{$marcacao['externalWorkplaceId']}', 
                    '{$marcacao['externalPersonId']}', 
                    CONVERT(DATETIME, '{$marcacao['registerDate']}', 120),
                    '', 
                    {$marcacao['directionTypeId']}, 
                    '{$marcacao['clockingTypeName']}',
                    '{$marcacao['deviceCode']}', 
                    '{$marcacao['timezone']}', 
                    '{$marcacao['workplaceTimezone']}', 
                    '{$marcacao['clockingCollectorName']}', 
                    '{$marcacao['ip']}', 
                    {$marcacao['identificationMethodId']}, 
                    {$marcacao['clockingCollectorId']}, 
                    {$marcacao['clockingTypeId']}, 
                    CONVERT(DATETIME, '{$marcacao['clockingDate']}', 120),
                    CONVERT(DATETIME, '{$marcacao['referenceDate']}', 120),
                    0,
                    0,
                    '{$marcacao['latitude']}',
                    '{$marcacao['longitude']}',
                    {$marcacao['clockingOriginId']},
                    {$removed}
                )
            ";

            DB::connection('sqlsrv')->statement($sql);
            $this->info("Marcação {$marcacao['id']} foi inserida com sucesso.");
        }
    }

    private function getMarcacao($id) {
        $sql = "SELECT * FROM ".env('DB_OWNER')."NEXTI_RET_MARCACOES WHERE ID = {$id}";
        return DB::connection('sqlsrv')->select($sql);
    }

    private function removeRegistro($id)
    {
        $sql = "DELETE FROM NEXTI_RET_MARCACOES WHERE ID = {$id}";
        DB::connection('sqlsrv')->statement($sql);
        $this->info("Marcação {$id} removido com sucesso!");
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->restClient = new \App\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->buscaTodasMarcacoes();
    }
}