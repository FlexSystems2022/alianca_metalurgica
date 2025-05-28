<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;
use DateInterval;

class BuscaApuracoes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaApuracoes';

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

    private function buscaPeriodo(){
        $query = 
        "
            SELECT 
            MIN(".env('DB_OWNER')."NEXTI_PERIODOS.startDate) AS STARTDATE,
            MAX(".env('DB_OWNER')."NEXTI_PERIODOS.finishDate) AS FINISHDATE
            FROM ".env('DB_OWNER')."NEXTI_PERIODOS
            --WHERE MONTH(".env('DB_OWNER')."NEXTI_PERIODOS.finishDate) = MONTH(GETDATE())
            --AND YEAR(".env('DB_OWNER')."NEXTI_PERIODOS.finishDate) = YEAR(GETDATE())
            WHERE ".env('DB_OWNER')."NEXTI_PERIODOS.ID IN(67001,69301,66999,66490,66998)
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function BuscaApuracoes()
    {
        //$periodo = $this->buscaPeriodo();
        $inicio = new DateTime();
        $inicio->sub(new DateInterval('P40D'));
        $fim = new DateTime();
        $count = 1;
        do {    

            $referenceDate = DateTime::createFromFormat('dmYHis', $inicio->format('dmYHis'));
            $referenceDate = $referenceDate->format('dmY000000');

            $this->info("Buscando apuracoes...{$referenceDate}");
            $this->BuscaApuracaoRecursiva(0, $referenceDate);
            $inicio->format('Y-m-d H:i:s');
            $inicio->modify("+1 day");
            $inicio = $inicio;
            var_dump($count++);
            sleep(20);
        } while (strtotime($inicio->format('Y-m-d')) <= strtotime($fim->format('Y-m-d')));
    }

    private function BuscaApuracaoRecursiva($page = 0, $referenceDate)
    {
        //$referenceDate = date('dmYHis');
        $response = $this->restClient->get("timetrackings/eventsperday/{$referenceDate}", [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar apurações: {$errors}");
        } else {
            $responseData = $this->restClient->getResponseData();
            if ($responseData) {
                $content = $responseData['value'] ?? [];

                if (sizeof($content) > 0) {
                    foreach ($content as $reg) {
                        $reg['referenceDate'] = DateTime::createFromFormat('dmYHis', $reg['referenceDate'])->format('Y-m-d H:i:s');

                        $existe = $this->verificaExiste($reg);
						
                        if(sizeof($existe) <= 0){
                            $this->addApuracao($reg);
                            //usleep(10000);
                        }else{
                            $this->atualizaApuracao($reg);
                            //usleep(10000);
                        }
                    }
                }

                // // Chamada recursiva para carregar proxima pagina
                // $totalPages = $responseData['totalPages'];
                // if ($page < $totalPages) {
                //     $this->BuscaApuracaoRecursiva($page + 1);
                // }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function verificaExiste($reg){
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."NEXTI_APURACAO
            WHERE ".env('DB_OWNER')."NEXTI_APURACAO.personId = {$reg['personId']}
            AND ".env('DB_OWNER')."NEXTI_APURACAO.referenceDate = CONVERT(DATETIME, '{$reg['referenceDate']}', 120)
            AND ".env('DB_OWNER')."NEXTI_APURACAO.timeTrackingTypeName = '{$reg['timeTrackingTypeName']}'
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function addApuracao($reg){
        $reg['currentWorkplaceExternalId'] = $reg['currentWorkplaceExternalId']??0;
        $reg['absenceSituationExternalId'] = $reg['absenceSituationExternalId']??0;
        $reg['timeTrackingTypeName'] = $reg['timeTrackingTypeName']??0;
        $reg['personId'] = $reg['personId']??0;
        $reg['workplaceId'] = $reg['workplaceId']??0;
        $reg['currentWorkplaceId'] = $reg['currentWorkplaceId']??0;
        $reg['workplaceCostCenter'] = $reg['workplaceCostCenter']??0;
        $reg['referenceDate'] = $reg['referenceDate']??0;
        $reg['personExternalId'] = $reg['personExternalId']??0;
        $reg['workplaceExternalId'] = $reg['workplaceExternalId']??0;
        $reg['minutes'] = $reg['minutes']??0;
        $reg['tipo'] = 0;
        $reg['situacao'] = 0;

        $query = 
        "
            INSERT INTO ".env('DB_OWNER')."NEXTI_APURACAO(
                currentWorkplaceExternalId,
                absenceSituationExternalId,
                timeTrackingTypeName,
                personId,
                workplaceId,
                currentWorkplaceId,
                workplaceCostCenter,
                referenceDate,personExternalId,
                workplaceExternalId,
                minutes,
                tipo,
                situacao
            )
            VALUES(
                '{$reg['currentWorkplaceExternalId']}',
                '{$reg['absenceSituationExternalId']}',
                '{$reg['timeTrackingTypeName']}',
                {$reg['personId']},
                {$reg['workplaceId']},
                {$reg['currentWorkplaceId']},
                '{$reg['workplaceCostCenter']}',
                Convert(DATETIME,'{$reg['referenceDate']}',120),
                '{$reg['personExternalId']}',
                '{$reg['workplaceExternalId']}',
                {$reg['minutes']},
                {$reg['tipo']},
                {$reg['situacao']}
            )
        ";

        DB::connection('sqlsrv')->statement($query);

        $this->info("Apuração {$reg['personExternalId']} Inserido com Sucesso!");
    }

    public function atualizaApuracao($reg){
        $reg['currentWorkplaceExternalId'] = $reg['currentWorkplaceExternalId']??0;
        $reg['absenceSituationExternalId'] = $reg['absenceSituationExternalId']??0;
        $reg['timeTrackingTypeName'] = $reg['timeTrackingTypeName']??0;
        $reg['personId'] = $reg['personId']??0;
        $reg['workplaceId'] = $reg['workplaceId']??0;
        $reg['currentWorkplaceId'] = $reg['currentWorkplaceId']??0;
        $reg['workplaceCostCenter'] = $reg['workplaceCostCenter']??0;
        $reg['referenceDate'] = $reg['referenceDate']??0;
        $reg['personExternalId'] = $reg['personExternalId']??0;
        $reg['workplaceExternalId'] = $reg['workplaceExternalId']??0;
        $reg['minutes'] = $reg['minutes']??0;
        $reg['tipo'] = 0;
        $reg['situacao'] = 0;

        $query = 
        "
            UPDATE ".env('DB_OWNER')."NEXTI_APURACAO
            SET
            currentWorkplaceExternalId = '{$reg['currentWorkplaceExternalId']}',
            absenceSituationExternalId = '{$reg['absenceSituationExternalId']}',
            timeTrackingTypeName = '{$reg['timeTrackingTypeName']}',
            personId = {$reg['personId']},
            workplaceId = {$reg['workplaceId']},
            currentWorkplaceId = {$reg['currentWorkplaceId']},
            workplaceCostCenter = '{$reg['workplaceCostCenter']}',
            referenceDate = Convert(DATETIME,'{$reg['referenceDate']}',120),
            personExternalId = '{$reg['personExternalId']}',
            workplaceExternalId = '{$reg['workplaceExternalId']}',
            minutes = {$reg['minutes']}
            WHERE ".env('DB_OWNER')."NEXTI_APURACAO.personId = {$reg['personId']}
            AND ".env('DB_OWNER')."NEXTI_APURACAO.referenceDate = Convert(DATETIME,'{$reg['referenceDate']}',120)
            AND ".env('DB_OWNER')."NEXTI_APURACAO.timeTrackingTypeName = '{$reg['timeTrackingTypeName']}'
        ";

        // "
        //     UPDATE ".env('DB_OWNER')."NEXTI_APURACAO
        //     SET 
        //     EXTERNALID = '{$reg['externalId']}', 
        //     NAME = '{$reg['name']}', 
        //     ENROLMENT = '{$reg['enrolment']}', 
        //     COMPANYID = {$reg['companyId']}, 
        //     PERSONTYPEID = {$reg['personTypeId']}, 
        //     PERSONSITUATIONID = {$reg['personSituationId']}, 
        //     CPF = '{$reg['cpf']}', 
        //     PIS = '{$reg['pis']}'
        //     WHERE ID = {$reg['id']}
        // ";

        DB::connection('sqlsrv')->statement($query);

        $this->info("Colaborador {$reg['personExternalId']} Atualizado com Sucesso!");
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

        //$this->BuscaApuracaoRecursiva();
        $this->BuscaApuracoes();
    }
}