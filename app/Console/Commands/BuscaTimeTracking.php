<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use DateTime;
use DateInterval;

class BuscaTimeTracking extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'BuscaTimeTracking';

    /**
     * The console command description.
     */
    protected $description = 'Command description';

    private $restClient = null;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');

        parent::__construct();
    }

    private function getResponseErrors()
    {
        $responseData = $this->restClient->getResponseData();

        $message = data_get($responseData, 'message', '');
        $comments = '';
        if (isset($responseData['comments']) && sizeof($responseData['comments']) > 0) {
            $comments = $responseData['comments'][0];
        }

        if ($comments != '') {
            $message = $comments;
        }

        return $message;
    }

    private function buscaEmpresas(): array
    {
        return DB::connection('sqlsrv')
            ->table(env('DB_OWNER')."NEXTI_EMPRESA")
            ->get()
            ->toArray();
    }

    private function buscarTimeTracking(string $start, string $finish, object $empresa): void
    {
        $response = $this->restClient->get("timetrackings/workedhours/companyexternalid/{$empresa->IDEXTERNO}/startdate/{$start}/finishdate/{$finish}", []);

        if (!$response->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();

            $this->info("Problema buscar timetracking: {$errors}");
            return;
        }

        $responseData = $response->getResponseData();
        if (!$responseData) {
            $this->info("Não foi possível ler a resposta da consulta.");
            return;
        }

        $content = data_get($responseData, 'value', []);
        foreach ($content as $reg) {
            $this->adicionarTimeTracking($reg);

            //usleep(1000);
        }
    }

    private function adicionarTimeTracking(array $data): void
    {
        $data['referenceDate'] = Carbon::createFromFormat('dmYHis', $data['referenceDate'])->format('Y-m-d H:i:s');

        $data['hasAbsence'] = $data['hasAbsence'] == true ? 1 : 0;
        $data['hasDayOff'] = $data['hasDayOff'] == true ? 1 : 0;
        $data['hasAllShiftAbsence'] = $data['hasAllShiftAbsence'] == true ? 1 : 0;
        $data['hasHoliday'] = $data['hasHoliday'] == true ? 1 : 0;
        $data['workedHours'] = data_get($data, 'workedHours', 0) ?: 0;

        $data['workplaceId'] = data_get($data, 'workplaceId', 0);
        $data['workplaceExternalId'] = data_get($data, 'workplaceExternalId', 0);
        $data['personExternalId'] = data_get($data, 'personExternalId', 0);

        $existe = $this->buscaExisteTimeTracking($data);

        if ($existe) {
            DB::connection('sqlsrv')
                ->table(env('DB_OWNER')."NEXTI_TIMETRACKING")
                ->where('referenceDate', $data['referenceDate'])
                ->where('personId', $data['personId'])
                ->update([
                    'hasAllShiftAbsence' => $data['hasAllShiftAbsence'],
                    'costCenter' => $data['costCenter'],
                    'hasAbsence' => $data['hasAbsence'],
                    'hasDayOff' => $data['hasDayOff'],
                    'personId' => $data['personId'],
                    'workplaceId' => $data['workplaceId'],
                    'personExternalId' => $data['personExternalId'],
                    'workplaceExternalId' => $data['workplaceExternalId'],
                    'hasHoliday' => $data['hasHoliday'],
                    'workedHours' => $data['workedHours'],
                ]);

            $this->info("TIMETRACKING {$data['personId']} atualizado com sucesso!");
        } else {
            DB::connection('sqlsrv')
                ->table(env('DB_OWNER')."NEXTI_TIMETRACKING")
                ->insert([
                    'hasAllShiftAbsence' => $data['hasAllShiftAbsence'],
                    'costCenter' => $data['costCenter'],
                    'hasAbsence' => $data['hasAbsence'],
                    'hasDayOff' => $data['hasDayOff'],
                    'personId' => $data['personId'],
                    'workplaceId' => $data['workplaceId'],
                    'referenceDate' => $data['referenceDate'],
                    'personExternalId' => $data['personExternalId'],
                    'workplaceExternalId' => $data['workplaceExternalId'],
                    'hasHoliday' => $data['hasHoliday'],
                    'workedHours' => $data['workedHours'],
                ]);

            $this->info("TIMETRACKING {$data['personId']} FOI INSERIDO COM SUCESSO.");
        }
    }

    public function buscaExisteTimeTracking(array $data): ?object
    {
        return DB::connection('sqlsrv')
            ->table(env('DB_OWNER')."NEXTI_TIMETRACKING")
            ->where('referenceDate', $data['referenceDate'])
            ->where('personId', $data['personId'])
            ->first();
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

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $empresas = $this->buscaEmpresas();

        //$periodo = $this->buscaPeriodo();
        $inicio = new DateTime();
        $inicio->sub(new DateInterval('P40D'));
        $fim = new DateTime();

        foreach($empresas as $empresa) {
            $start = $inicio;
            $finish = $inicio;
            do {
                $this->info("Buscando timetracking... Empresa {$empresa->NUMEMP} - {$start->format('d/m/Y')} - {$finish->format('d/m/Y')}");
                $this->buscarTimeTracking($start->format('dmYHis'), $finish->format('dmY235959'), $empresa);

                $start->format('Y-m-d H:i:s');
                $start->modify("+1 day");
                $start = $start;

                sleep(15);
            } while(strtotime($start->format('Y-m-d')) <= strtotime($fim->format('Y-m-d')));

            $inicio = new DateTime();
            $inicio->sub(new DateInterval('P40D'));
            $fim = new DateTime();
            $start = $inicio;
            $finish = $inicio;
        }
    }
}