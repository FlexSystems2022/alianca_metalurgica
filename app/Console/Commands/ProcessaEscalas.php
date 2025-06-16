<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaEscalas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaEscalas';

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
            *
            FROM ".env('DB_OWNER')."FLEX_ESCALA
        ";
    }

    private function getTypeId($typeSenior)
    {
        $type = 7;
        switch (intval($typeSenior)) {
            case 11: $type = 7; break;
            case 1: $type = 1; break;
            case 2: $type = 2; break;
            case 3: $type = 7; break;
            case 4: $type = 7; break;
            case 5: $type = 4; break;
            case 6: $type = 7; break;
            case 7: $type = 7; break;
            case 8: $type = 7; break;
            case 8: $type = 7; break;
            case 12: $type = 7; break;
            case 10: $type = 5; break;
            
            default:
                $type = 7;
                break;
        }
        return $type;
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

    private function atualizaRegistro($externalId, $situacao, $id, $codesc, $escala)
    {
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado.';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_ESCALA 
            SET ".env('DB_OWNER')."FLEX_ESCALA.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_ESCALA.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."FLEX_ESCALA.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_ESCALA.IDEXTERNO = '{$externalId}'
        ");

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_ESCALA_TURMA
            SET ".env('DB_OWNER')."FLEX_ESCALA_TURMA.SITUACAO = 1
            WHERE ".env('DB_OWNER')."FLEX_ESCALA_TURMA.CODESC = '{$codesc}'
        ");

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_ESCALA_HORARIO
            SET ".env('DB_OWNER')."FLEX_ESCALA_HORARIO.SITUACAO = 1
            WHERE ".env('DB_OWNER')."FLEX_ESCALA_HORARIO.CODESC = '{$codesc}'
        ");
    }

    private function atualizaRegistroComErro($externalId, $situacao, $obs = '', $escala)
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_ESCALA 
            SET ".env('DB_OWNER')."FLEX_ESCALA.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_ESCALA.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_ESCALA.IDEXTERNO = '{$externalId}'
            AND ".env('DB_OWNER')."FLEX_ESCALA.NUMEMP = {$escala->NUMEMP}
            AND ".env('DB_OWNER')."FLEX_ESCALA.CODFIL = '{$escala->CODFIL}'
        ");
    }

    private function getEscalasNovos()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_ESCALA.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_ESCALA.SITUACAO IN (0,2)
            AND ".env('DB_OWNER')."FLEX_ESCALA.INTEGRA = 'S'
        ";
        $escalas = DB::connection('sqlsrv')->select($this->query . $where);
        
        return $escalas;
    }

    private function getEscalasAtualizar()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_ESCALA.TIPO = 1 
            AND ".env('DB_OWNER')."FLEX_ESCALA.SITUACAO IN (0,2)
            AND ".env('DB_OWNER')."FLEX_ESCALA.INTEGRA = 'S'
            
        ";

        //AND ".env('DB_OWNER')."FLEX_ESCALA.IDEXTERNO = '1035'

        $escalas = DB::connection('sqlsrv')->select($this->query . $where);

        return $escalas;
    }

    private function getHorariosDaEscala($escala)
    {
        $query =
        "
            SELECT
            ".env('DB_OWNER')."FLEX_ESCALA_HORARIO.*,
            ".env('DB_OWNER')."FLEX_HORARIO.ID AS IDHORARIO,
            ".env('DB_OWNER')."FLEX_HORARIO.IDEXTERNO AS EXTERNALIDHORARIO
            FROM ".env('DB_OWNER')."FLEX_ESCALA_HORARIO
            LEFT JOIN ".env('DB_OWNER')."FLEX_HORARIO
                ON ".env('DB_OWNER')."FLEX_HORARIO.CODHOR = ".env('DB_OWNER')."FLEX_ESCALA_HORARIO.CODHOR
            WHERE ".env('DB_OWNER')."FLEX_ESCALA_HORARIO.CODESC = '{$escala->CODESC}'
            ORDER BY ".env('DB_OWNER')."FLEX_ESCALA_HORARIO.SEMANA,
            IIF(".env('DB_OWNER')."FLEX_ESCALA_HORARIO.DIA = 1, 8, ".env('DB_OWNER')."FLEX_ESCALA_HORARIO.DIA)
        ";

        $horarios = DB::connection('sqlsrv')->select($query);
       
        return $horarios;
    }

    private function enviaEscalasNovos()
    {
        $this->info("Iniciando envio:");
        $escalas = $this->getEscalasNovos();
        $nEscalas = sizeof($escalas);

        foreach ($escalas as $escala) {
            $scheduleShiftsEscala = $this->getHorariosDaEscala($escala);
            $scheduleShifts = [];
            $seq = 1;

            foreach ($scheduleShiftsEscala as $shift) {
                $idHorario = $shift->IDHORARIO;

                if($shift->TIPO_DIA == 'D'){
                    $idHorario = 14465396;
                }elseif($shift->TIPO_DIA == 'C'){
                    $idHorario = 14465397;
                }

                $_shift = array(
                    'shiftId' => intval($idHorario),
                    'sequence' => $seq,
                );
                $scheduleShifts[] = $_shift;
                $seq++;
            }

            $regra = $this->buscaRegraIntervaloPreAssinado($escala);
        
            $escalaNexti = [
                'externalId'        => $escala->IDEXTERNO,
                'premarkedInterval' => isset($regra[0]->PREMARKEDINTERVAL) && intval($regra[0]->PREMARKEDINTERVAL) == 1 ? true : false,
                'ignoreHolidays'    => false,
                'ignoreInterval'    => false,
                'scheduleTypeId'    => 7,
                'name'              => trim($escala->NOMESC),
                'rotations'         => []
            ];
            //dd($escala);

            if (sizeof($scheduleShifts) > 0) {
                $escalaNexti['scheduleShifts'] = $scheduleShifts;
            }else{
                $_shift = array(
                    'shiftId' => 14465411,
                    'sequence' => 1,
                );

                $scheduleShifts[] = $_shift;
                $escalaNexti['scheduleShifts'] = $scheduleShifts;
            }

            $sqlTurmas =
            "
                SELECT
                ".env('DB_OWNER')."FLEX_ESCALA_TURMA.CODESC,
                ".env('DB_OWNER')."FLEX_ESCALA_TURMA.*,
                ".env('DB_OWNER')."FLEX_ESCALA.ID AS IDESCALA,
                ".env('DB_OWNER')."FLEX_ESCALA.IDEXTERNO AS EXTERNALIDESCALA
                FROM ".env('DB_OWNER')."FLEX_ESCALA_TURMA
                JOIN ".env('DB_OWNER')."FLEX_ESCALA
                    ON ".env('DB_OWNER')."FLEX_ESCALA.CODESC = ".env('DB_OWNER')."FLEX_ESCALA_TURMA.CODESC
                    AND ".env('DB_OWNER')."FLEX_ESCALA.NUMEMP = ".env('DB_OWNER')."FLEX_ESCALA_TURMA.NUMEMP
                    AND ".env('DB_OWNER')."FLEX_ESCALA.CODFIL = ".env('DB_OWNER')."FLEX_ESCALA_TURMA.CODFIL
                AND ".env('DB_OWNER')."FLEX_ESCALA_TURMA.CODESC = '{$escala->CODESC}'
                AND ".env('DB_OWNER')."FLEX_ESCALA_TURMA.NUMEMP = {$escala->NUMEMP}
                AND ".env('DB_OWNER')."FLEX_ESCALA_TURMA.CODFIL = '{$escala->CODFIL}'
            ";

            $turmas = DB::connection('sqlsrv')->select($sqlTurmas);
            
            $turmasArray = [];
            $dataTurma = date("Y-m-d", strtotime("1939-12-31"));
            if($turmas){
                foreach ($turmas as $k => $turma) {
                    if($turma->TIPO == 99){
                        array_push($turmasArray, [
                            'baseDateTime'  => '31121940000000',
                            'code'          => 1
                        ]);
                    }else{
                        $dataTurma = date("Y-m-d", strtotime("+1 day", strtotime($dataTurma)));

                        array_push($turmasArray, [
                            'baseDateTime'  => date("dmY", strtotime($dataTurma)) . '000000',
                            'code'          => intval($turma->CODTMA)
                        ]);
                    }
                }
            }else{
                array_push($turmasArray, [
                    'baseDateTime'  => '31121940000000',
                    'code'          => 1
                ]);
            }

            $escalaNexti['rotations'] = $turmasArray;

            //dd($escalaNexti);

           $ret = $this->enviaEscalaNovo($escalaNexti, $escala->CODESC, $escala);
           sleep(1);
        }
        $this->info("Total de {$nEscalas} escalas(s) cadastrados!");
    }


    private function enviaEscalaNovo($escalaNexti, $codesc, $escala)
    {
        $name = $escalaNexti['name'];
        
        $this->info("Enviando o escala {$name} para API Nexti");
        
        $response = $this->restClient->post("schedules/dto", [], [
            'json' => $escalaNexti
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o escala {$name} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($escalaNexti['externalId'], 2, $errors, $escala);
        } else {
            $this->info("Escala {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $escalaInserido = $responseData['value'];
            $id = $escalaInserido['id'];

            $this->atualizaRegistro($escalaNexti['externalId'], 1, $id, $codesc, $escala);
            $this->atualizarHorariosTurmas($codesc, $escala);
        }
    }

    private function enviaEscalasAtualizar()
    {
        $this->info("Inicializando atualização:");
        $escalas = $this->getEscalasAtualizar();
        $nEscalas = sizeof($escalas);

        foreach ($escalas as $escala) {
            $scheduleShiftsEscala = $this->getHorariosDaEscala($escala);
            $scheduleShifts = [];
            $seq = 1;

            foreach ($scheduleShiftsEscala as $shift) {
                $idHorario = $shift->IDHORARIO;

                if($shift->TIPO_DIA == 'D'){
                    $idHorario = 14465396;
                }elseif($shift->TIPO_DIA == 'C'){
                    $idHorario =    14465397;
                }

                $_shift = array(
                    'shiftId' => intval($idHorario),
                    // 'shiftExternalId' => intval($shift->IDEXTERNOHORARIO),
                    'sequence' => $seq,
                );
                $scheduleShifts[] = $_shift;
                $seq++;
            }

            $regra = $this->buscaRegraIntervaloPreAssinado($escala);
        
            $escalaNexti = [
                //'externalId'        => $escala->IDEXTERNO,
                'name'              => trim($escala->NOMESC),
                //'scheduleTypeId'    => 7,
                'rotations'         => [],
                'premarkedInterval' => isset($regra[0]->PREMARKEDINTERVAL) && intval($regra[0]->PREMARKEDINTERVAL) == 1 ? true : false,
            ];
            //dd($escala);

            if (sizeof($scheduleShifts) > 0) {
                $escalaNexti['scheduleShifts'] = $scheduleShifts;
            }else{
                $_shift = array(
                    'shiftId' => 14465411,
                    'sequence' => 1,
                );

                $scheduleShifts[] = $_shift;
                $escalaNexti['scheduleShifts'] = $scheduleShifts;
            }

            $sqlTurmas =
            "
                SELECT
                ".env('DB_OWNER')."FLEX_ESCALA_TURMA.CODESC,
                ".env('DB_OWNER')."FLEX_ESCALA_TURMA.*,
                ".env('DB_OWNER')."FLEX_ESCALA.ID AS IDESCALA,
                ".env('DB_OWNER')."FLEX_ESCALA.IDEXTERNO AS EXTERNALIDESCALA
                FROM ".env('DB_OWNER')."FLEX_ESCALA_TURMA
                JOIN ".env('DB_OWNER')."FLEX_ESCALA
                    ON ".env('DB_OWNER')."FLEX_ESCALA.CODESC = ".env('DB_OWNER')."FLEX_ESCALA_TURMA.CODESC
                    AND ".env('DB_OWNER')."FLEX_ESCALA.NUMEMP = ".env('DB_OWNER')."FLEX_ESCALA_TURMA.NUMEMP
                    AND ".env('DB_OWNER')."FLEX_ESCALA.CODFIL = ".env('DB_OWNER')."FLEX_ESCALA_TURMA.CODFIL
                AND ".env('DB_OWNER')."FLEX_ESCALA_TURMA.CODESC = '{$escala->CODESC}'
                AND ".env('DB_OWNER')."FLEX_ESCALA_TURMA.NUMEMP = {$escala->NUMEMP}
                AND ".env('DB_OWNER')."FLEX_ESCALA_TURMA.CODFIL = '{$escala->CODFIL}'
            ";

            $turmas = DB::connection('sqlsrv')->select($sqlTurmas);
            
            $turmasArray = [];
            $dataTurma = date("Y-m-d", strtotime("1939-12-31"));
            if($turmas){
                foreach ($turmas as $k => $turma) {
                    if($turma->TIPO == 99){
                        array_push($turmasArray, [
                            'baseDateTime'  => '01011940000000',
                            'code'          => intval($turma->CODTMA)
                        ]);
                    }else{
                        $dataTurma = date("Y-m-d", strtotime("+1 day", strtotime($dataTurma)));

                        array_push($turmasArray, [
                            'baseDateTime'  => date("dmY", strtotime($dataTurma)) . '000000',
                            'code'          => intval($turma->CODTMA)
                        ]);
                    }
                }
            }else{
                array_push($turmasArray, [
                    'baseDateTime'  => '01011940000000',
                    'code'          => 1
                ]);
            }

            $escalaNexti['rotations'] = $turmasArray;

            //var_dump(json_encode($escalaNexti));
            //var_dump($escalaNexti);

            $ret = $this->processaEscalaAtualizar($escala->IDEXTERNO, $escalaNexti, $escala->ID, $escala->CODESC, $escala);
            //sleep(1);
        }

        $this->info("Total de {$nEscalas} escalas(s) atualizados!");
    }

    private function insertLogs($msg, $dataInicio, $dataFim)
    {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime,'{$dataInicio}',120), convert(datetime,'{$dataFim}',120), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }

    private function processaEscalaAtualizar($externalId, $escalaNexti, $id, $codesc, $escala)
    {
        $this->info("Atualizando o escala {$externalId} via API Nexti");

        $endpoint = "schedules/dto/externalId/{$externalId}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $escalaNexti
        ])->getResponse();

        //var_dump($response);
        //dd($response);
        if (!$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o escala {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors, $escala);
        } else {
            $this->info("Escala {$externalId} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $escalaInserido = $responseData['value'];
            $id = $escalaInserido['id'];

            $this->atualizaRegistro($externalId, 1, $id, $codesc, $escala);
            $this->atualizarHorariosTurmas($codesc, $escala);
        }
    }

    private function buscaRegraIntervaloPreAssinado($escala){
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."FLEX_ESCALA_REGRA
            WHERE ".env('DB_OWNER')."FLEX_ESCALA_REGRA.NUMEMP = {$escala->NUMEMP}
            AND ".env('DB_OWNER')."FLEX_ESCALA_REGRA.CODFIL = '{$escala->CODFIL}'
            AND ".env('DB_OWNER')."FLEX_ESCALA_REGRA.CODESC = '{$escala->CODESC}'
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    public function atualizarHorariosTurmas($idExterno){
        $queryHorarios = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_ESCALA_HORARIO
            SET SITUACAO = 1 WHERE CODESC = '{$idExterno}'
        ";

        DB::connection('sqlsrv')->statement($queryHorarios);

        $queryTurmas = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_ESCALA_TURMA
            SET SITUACAO = 1 WHERE CODESC = '{$idExterno}'
        ";

        DB::connection('sqlsrv')->statement($queryTurmas);
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

        $this->enviaEscalasAtualizar();
        $this->enviaEscalasNovos();
    }
}
