<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaHorarios extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaHorarios';

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
            ".env('DB_OWNER')."FLEX_HORARIO.*,*
            FROM ".env('DB_OWNER')."FLEX_HORARIO 
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

    private function atualizaRegistro($externalId, $situacao, $id) {
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado.';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_HORARIO 
            SET ".env('DB_OWNER')."FLEX_HORARIO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_HORARIO.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."FLEX_HORARIO.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_HORARIO.IDEXTERNO = '{$externalId}'
        ");
    } 

    private function atualizaRegistroComErro($externalId, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_HORARIO 
            SET ".env('DB_OWNER')."FLEX_HORARIO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_HORARIO.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_HORARIO.IDEXTERNO = '{$externalId}'
        ");
    } 

    private function getHorariosNovos()
    {   
        $where = " 
            WHERE ".env('DB_OWNER')."FLEX_HORARIO.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_HORARIO.SITUACAO IN (0,2,99)
        ";
        $horarios = DB::connection('sqlsrv')->select($this->query . $where);

        return $horarios;
    }

    private function getHorariosExcluir()
    {   
        $where = " 
            WHERE ".env('DB_OWNER')."FLEX_HORARIO.TIPO = 3 
            AND ".env('DB_OWNER')."FLEX_HORARIO.SITUACAO IN (0,2,99)
        ";
        $horarios = DB::connection('sqlsrv')->select($this->query . $where);

        return $horarios;
    }

    private function consultaRegras($horario){
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."FLEX_HORARIO_REGRA
            WHERE ".env('DB_OWNER')."FLEX_HORARIO_REGRA.NUMEMP = {$horario->NUMEMP}
            AND ".env('DB_OWNER')."FLEX_HORARIO_REGRA.CODFIL = '{$horario->CODFIL}'
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function enviaHorariosNovos() {
        $this->info("Iniciando envio:");
        $horarios = $this->getHorariosNovos();
        $nHorarios = sizeof($horarios);
        foreach ($horarios as $horario) {
            $markings = [];

            $ENTRADA1 = floatval($horario->ENTRADA1) == 0 ? '00:00' : substr(str_replace('.', ':', $horario->ENTRADA1 > 0 && $horario->ENTRADA1 < 1 ? floatval($horario->ENTRADA1): $horario->ENTRADA1), 0, 5);
            $SAIDA1 = floatval($horario->SAIDA1) == 0 ? '00:00' : substr(str_replace('.', ':', $horario->SAIDA1 > 0 && $horario->SAIDA1 < 1 ? floatval($horario->SAIDA1): $horario->SAIDA1), 0, 5);
            $ENTRADA2 = floatval($horario->ENTRADA2) == 0 ? '00:00' : substr(str_replace('.', ':', $horario->ENTRADA2 > 0 && $horario->ENTRADA2 < 1 ? floatval($horario->ENTRADA2): $horario->ENTRADA2), 0, 5);
            $SAIDA2 = floatval($horario->SAIDA2) == 0 ? '00:00' : substr(str_replace('.', ':', $horario->SAIDA2 > 0 && $horario->SAIDA2 < 1 ? floatval($horario->SAIDA2): $horario->SAIDA2), 0, 5);

            if($horario->ENTRADA1 == 0 && $horario->SAIDA1 ==0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 == 0){
                $markings = [];
            }

            $regras = $this->consultaRegras($horario);

            $mobilidadeAntes = null;
            $mobilidadeDepois = null;
            $toleranciaAntes = null;
            $toleranciaDepois = null;

            if($horario->ENTRADA1 == 0 && $horario->SAIDA1 ==0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 == 0){
                $mobilidadeAntes = null;
                $mobilidadeDepois = null;
                $toleranciaAntes = null;
                $toleranciaDepois = null;
            }else{
                $inicio1 = $this->horaParaMinutos($ENTRADA1);
                $fim1 = $this->horaParaMinutos($SAIDA1);
                $inicio2 = $this->horaParaMinutos($ENTRADA2);
                $fim2 = $this->horaParaMinutos($SAIDA2);

                $toleranciaAntes = ($fim1 - $inicio1) < 0 ? ($fim1 - $inicio1 + 1440 - 30) : ($fim1 - $inicio1 - 30);
                $toleranciaDepois = ($fim2 - $inicio2) < 0 ? ($fim2 - $inicio2 + 1440 - 30) : ($fim2 - $inicio2 - 30);
            }

            if($horario->ENTRADA1 > 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($ENTRADA1),
                    'markingTypeId'     => 1,
                    'sequence'          => 1,
                    'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaAntes,
                    'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10
                );
                array_push($markings, $_mark);
            }elseif($horario->ENTRADA1 == 0 && $horario->SAIDA1 > 0){
                $_mark = array(
                    'marking'           => 0,
                    'markingTypeId'     => 1,
                    'sequence'          => 1,
                    'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaAntes,
                    'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10
                );
                array_push($markings, $_mark);
            }

            if($horario->ENTRADA1 > 0 && $horario->SAIDA1 == 0 && $horario->ENTRADA2 == 0){
                $_mark = array(
                    'marking'           => 0,
                    'markingTypeId'     => 4,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => 0,
                    'markingTypeId'     => 4,
                    'sequence'          => 3,
                );
                array_push($markings, $_mark);

                if($horario->SAIDA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($SAIDA2),
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }else{
                    $_mark = array(
                        'marking'           => 0,
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }
            }elseif($horario->ENTRADA1 > 0 && $horario->SAIDA1 == 0 && $horario->ENTRADA2 > 0){
                $_mark = array(
                    'marking'           => 0,
                    'markingTypeId'     => 4,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => $this->horaParaMinutos($ENTRADA2),
                    'markingTypeId'     => 4,
                    'sequence'          => 3,
                );
                array_push($markings, $_mark);

                if($horario->SAIDA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($SAIDA2),
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }else{
                    $_mark = array(
                        'marking'           => 0,
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }
            }elseif($horario->ENTRADA1 > 0 && $horario->SAIDA1 > 0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 > 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 4,
                    'sequence'          => 2
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => $this->horaParaMinutos($ENTRADA2),
                    'markingTypeId'     => 4,
                    'sequence'          => 3
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA2),
                    'markingTypeId'     => 1,
                    'sequence'          => 4,
                    'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                    'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                );
                array_push($markings, $_mark);

            }elseif($horario->ENTRADA1 > 0 && $horario->SAIDA1 > 0 && $horario->ENTRADA2 == 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 1,
                    'sequence'          => 4,
                    'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                    'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                );
                array_push($markings, $_mark);

                // $_mark = array(
                //     'marking'           => 0,
                //     'markingTypeId'     => 4,
                //     'sequence'          => 3,
                // );
                // array_push($markings, $_mark);

                if($horario->SAIDA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($SAIDA2),
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }else{
                    // $_mark = array(
                    //     'marking'           => 0,
                    //     'markingTypeId'     => 1,
                    //     'sequence'          => 4,
                    //     'checkoutTolerance' => 10,
                    //     'checkinTolerance'  => 10
                    // );
                    // array_push($markings, $_mark);
                }
            }elseif($horario->SAIDA1 > 0 && $horario->ENTRADA2 > 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 4,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => $this->horaParaMinutos($ENTRADA2),
                    'markingTypeId'     => 4,
                    'sequence'          => 3,
                );
                array_push($markings, $_mark);

                if($horario->SAIDA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($SAIDA2),
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }else{
                    $_mark = array(
                        'marking'           => 0,
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }
            }elseif($horario->SAIDA1 > 0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 > 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 4,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => 0,
                    'markingTypeId'     => 4,
                    'sequence'          => 3,
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA2),
                    'markingTypeId'     => 1,
                    'sequence'          => 4,
                    'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                    'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                );
            }elseif($horario->SAIDA1 > 0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 == 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 1,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);
            }elseif($horario->SAIDA1 > 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 4,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);

                if($horario->ENTRADA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($ENTRADA2),
                        'markingTypeId'     => 4,
                        'sequence'          => 3,
                    );
                    array_push($markings, $_mark);
                }

                if($horario->SAIDA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($SAIDA2),
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }
            }

            $mobilidadeAntes = null;
            $mobilidadeDepois = null;

            if(sizeof($markings) == 4){
                if($horario->ENTRADA1 == 0 && $horario->SAIDA1 ==0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 == 0){
                    $mobilidadeAntes = null;
                    $mobilidadeDepois = null;
                    $toleranciaAntes = null;
                    $toleranciaDepois = null;
                }else{
                    $inicio1 = $this->horaParaMinutos($ENTRADA1);
                    $fim1 = $this->horaParaMinutos($SAIDA1);
                    $inicio2 = $this->horaParaMinutos($ENTRADA2);
                    $fim2 = $this->horaParaMinutos($SAIDA2);

                    $mobilidadeAntes = ($fim1 - $inicio1) < 0 ? ($fim1 - $inicio1 + 1440 - 30) : ($fim1 - $inicio1 - 30);
                    $mobilidadeDepois = ($fim2 - $inicio2) < 0 ? ($fim2 - $inicio2 + 1440 - 30) : ($fim2 - $inicio2 - 30);
                }
            }

            $mobilidadeDepois = $mobilidadeDepois > 360 ? 360 : $mobilidadeDepois;
            $mobilidadeAntes = $mobilidadeAntes > 360 ? 360 : $mobilidadeAntes;

            $mobilidadeAntes = $mobilidadeAntes <= 4 ? 5 : $mobilidadeAntes;
            $mobilidadeDepois = $mobilidadeDepois <= 4 ? 5 : $mobilidadeDepois;

            //var_dump($horario);
            $horarioNexti = [
                'shiftTypeId' => 5,
                'externalId' => $horario->IDEXTERNO,
                'name' => $horario->DESHOR,
                'active' => true,
                'markings' => $markings,
                'mobilityAfter' => 120,
                'mobilityBefore' => 120
            ];

            //var_dump($horarioNexti);exit;

            //var_dump($horarioNexti, $regras[0]->GERA_INCONSISTENCIA);exit;

            //var_dump($horarioNexti);exit;
            
            $ret = $this->enviaHorarioNovo($horarioNexti);
        }
        $this->info("Total de {$nHorarios} horarios(s) cadastrados!");
    }

    private function enviaHorarioNovo($horario) {
        $name = $horario['name'];
        $this->info("Enviando o horario {$name} para API Nexti");

        $response = $this->restClient->post("shifts", [], [
            'json' => $horario
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o horario {$name} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($horario['externalId'], 2, $errors);

        } else {
            $this->info("Horario {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $horarioInserido = $responseData['value'];
            $id = $horarioInserido['id'];

            $this->atualizaRegistro($horario['externalId'], 1, $id);
        }
    }

    private function getHorariosAtualizar()
    {
        $where = " 
            WHERE ".env('DB_OWNER')."FLEX_HORARIO.TIPO = 1 
            AND ".env('DB_OWNER')."FLEX_HORARIO.SITUACAO IN (0,2)
        ";
        $horarios = DB::connection('sqlsrv')->select($this->query . $where);

        return $horarios;
    }

    function horaParaMinutos($hora){
        $partes = explode(":", $hora);
        $partes[1] = substr($partes[1], 0, 2);
        $minutos = $partes[0]*60+$partes[1];
        return ($minutos);
    }

    private function enviaHorariosAtualizar() {
        $this->info("Inicializando atualização:");
        $horarios = $this->getHorariosAtualizar();
        $nHorarios = sizeof($horarios);

        foreach ($horarios as $horario) {
            $markings = [];

            $ENTRADA1 = floatval($horario->ENTRADA1) == 0 ? '00:00' : substr(str_replace('.', ':', $horario->ENTRADA1 > 0 && $horario->ENTRADA1 < 1 ? floatval($horario->ENTRADA1): $horario->ENTRADA1), 0, 5);
            $SAIDA1 = floatval($horario->SAIDA1) == 0 ? '00:00' : substr(str_replace('.', ':', $horario->SAIDA1 > 0 && $horario->SAIDA1 < 1 ? floatval($horario->SAIDA1): $horario->SAIDA1), 0, 5);
            $ENTRADA2 = floatval($horario->ENTRADA2) == 0 ? '00:00' : substr(str_replace('.', ':', $horario->ENTRADA2 > 0 && $horario->ENTRADA2 < 1 ? floatval($horario->ENTRADA2): $horario->ENTRADA2), 0, 5);
            $SAIDA2 = floatval($horario->SAIDA2) == 0 ? '00:00' : substr(str_replace('.', ':', $horario->SAIDA2 > 0 && $horario->SAIDA2 < 1 ? floatval($horario->SAIDA2): $horario->SAIDA2), 0, 5);

            if($horario->ENTRADA1 == 0 && $horario->SAIDA1 ==0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 == 0){
                $markings = [];
            }

            $regras = $this->consultaRegras($horario);

            $mobilidadeAntes = null;
            $mobilidadeDepois = null;
            $toleranciaAntes = null;
            $toleranciaDepois = null;

            if($horario->ENTRADA1 == 0 && $horario->SAIDA1 ==0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 == 0){
                $mobilidadeAntes = null;
                $mobilidadeDepois = null;
                $toleranciaAntes = null;
                $toleranciaDepois = null;
            }else{
                $inicio1 = $this->horaParaMinutos($ENTRADA1);
                $fim1 = $this->horaParaMinutos($SAIDA1);
                $inicio2 = $this->horaParaMinutos($ENTRADA2);
                $fim2 = $this->horaParaMinutos($SAIDA2);

                $toleranciaAntes = ($fim1 - $inicio1) < 0 ? ($fim1 - $inicio1 + 1440 - 30) : ($fim1 - $inicio1 - 30);
                $toleranciaDepois = ($fim2 - $inicio2) < 0 ? ($fim2 - $inicio2 + 1440 - 30) : ($fim2 - $inicio2 - 30);

                if($inicio2 == 0 && $fim2 == 0){
                    $toleranciaDepois = $toleranciaAntes;
                }
            }

            if($horario->ENTRADA1 > 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($ENTRADA1),
                    'markingTypeId'     => 1,
                    'sequence'          => 1,
                    'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaAntes,
                    'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10
                );
                array_push($markings, $_mark);
            }elseif($horario->ENTRADA1 == 0 && $horario->SAIDA1 > 0){
                $_mark = array(
                    'marking'           => 0,
                    'markingTypeId'     => 1,
                    'sequence'          => 1,
                    'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaAntes,
                    'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10
                );
                array_push($markings, $_mark);
            }

            if($horario->ENTRADA1 > 0 && $horario->SAIDA1 == 0 && $horario->ENTRADA2 == 0){
                $_mark = array(
                    'marking'           => 0,
                    'markingTypeId'     => 4,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => 0,
                    'markingTypeId'     => 4,
                    'sequence'          => 3,
                );
                array_push($markings, $_mark);

                if($horario->SAIDA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($SAIDA2),
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }else{
                    $_mark = array(
                        'marking'           => 0,
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }
            }elseif($horario->ENTRADA1 > 0 && $horario->SAIDA1 == 0 && $horario->ENTRADA2 > 0){
                $_mark = array(
                    'marking'           => 0,
                    'markingTypeId'     => 4,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => $this->horaParaMinutos($ENTRADA2),
                    'markingTypeId'     => 4,
                    'sequence'          => 3,
                );
                array_push($markings, $_mark);

                if($horario->SAIDA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($SAIDA2),
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }else{
                    $_mark = array(
                        'marking'           => 0,
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }
            }elseif($horario->ENTRADA1 > 0 && $horario->SAIDA1 > 0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 > 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 4,
                    'sequence'          => 2
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => $this->horaParaMinutos($ENTRADA2),
                    'markingTypeId'     => 4,
                    'sequence'          => 3
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA2),
                    'markingTypeId'     => 1,
                    'sequence'          => 4,
                    'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                    'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                );
                array_push($markings, $_mark);

            }elseif($horario->ENTRADA1 > 0 && $horario->SAIDA1 > 0 && $horario->ENTRADA2 == 0){
                $horario->DESHOR = str_replace(" / 00:00 - 00:00","",$horario->DESHOR);
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 1,
                    'sequence'          => 4,
                    'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                    'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                );
                array_push($markings, $_mark);

                // $_mark = array(
                //     'marking'           => 0,
                //     'markingTypeId'     => 4,
                //     'sequence'          => 3,
                // );
                // array_push($markings, $_mark);

                if($horario->SAIDA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($SAIDA2),
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }
            }elseif($horario->SAIDA1 > 0 && $horario->ENTRADA2 > 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 4,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => $this->horaParaMinutos($ENTRADA2),
                    'markingTypeId'     => 4,
                    'sequence'          => 3,
                );
                array_push($markings, $_mark);

                if($horario->SAIDA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($SAIDA2),
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }else{
                    $_mark = array(
                        'marking'           => 0,
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }
            }elseif($horario->SAIDA1 > 0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 > 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 4,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => 0,
                    'markingTypeId'     => 4,
                    'sequence'          => 3,
                );
                array_push($markings, $_mark);

                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA2),
                    'markingTypeId'     => 1,
                    'sequence'          => 4,
                    'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                    'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                );
            }elseif($horario->SAIDA1 > 0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 == 0){
                $horario->DESHOR = str_replace(" / 00:00 - 00:00","",$horario->DESHOR);

                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 1,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);
            }elseif($horario->SAIDA1 > 0){
                $_mark = array(
                    'marking'           => $this->horaParaMinutos($SAIDA1),
                    'markingTypeId'     => 4,
                    'sequence'          => 2,
                );
                array_push($markings, $_mark);

                if($horario->ENTRADA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($ENTRADA2),
                        'markingTypeId'     => 4,
                        'sequence'          => 3,
                    );
                    array_push($markings, $_mark);
                }

                if($horario->SAIDA2 > 0){
                    $_mark = array(
                        'marking'           => $this->horaParaMinutos($SAIDA2),
                        'markingTypeId'     => 1,
                        'sequence'          => 4,
                        'checkoutTolerance' => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : 10,
                        'checkinTolerance'  => !isset($regras[0]->TOLERANCIA) || $regras[0]->TOLERANCIA == 0 ? null : $toleranciaDepois,
                    );
                    array_push($markings, $_mark);
                }
            }

            $mobilidadeAntes = null;
            $mobilidadeDepois = null;

            if(sizeof($markings) == 4){
                if($horario->ENTRADA1 == 0 && $horario->SAIDA1 ==0 && $horario->ENTRADA2 == 0 && $horario->SAIDA2 == 0){
                    $mobilidadeAntes = null;
                    $mobilidadeDepois = null;
                    $toleranciaAntes = null;
                    $toleranciaDepois = null;
                }else{
                    $inicio1 = $this->horaParaMinutos($ENTRADA1);
                    $fim1 = $this->horaParaMinutos($SAIDA1);
                    $inicio2 = $this->horaParaMinutos($ENTRADA2);
                    $fim2 = $this->horaParaMinutos($SAIDA2);

                    $mobilidadeAntes = ($fim1 - $inicio1) < 0 ? ($fim1 - $inicio1 + 1440 - 30) : ($fim1 - $inicio1 - 30);
                    $mobilidadeDepois = ($fim2 - $inicio2) < 0 ? ($fim2 - $inicio2 + 1440 - 30) : ($fim2 - $inicio2 - 30);
                }
            }

            $mobilidadeDepois = $mobilidadeDepois > 360 ? 360 : $mobilidadeDepois;
            $mobilidadeAntes = $mobilidadeAntes > 360 ? 360 : $mobilidadeAntes;

            $mobilidadeAntes = $mobilidadeAntes <= 4 ? 5 : $mobilidadeAntes;
            $mobilidadeDepois = $mobilidadeDepois <= 4 ? 5 : $mobilidadeDepois;

            $horarioNexti = [
                'shiftTypeId' => 5,
                'externalId' => $horario->IDEXTERNO,
                'name' => $horario->DESHOR,
                //'active' => true,
                'markings' => $markings,
                'mobilityAfter' => 120,
                'mobilityBefore' => 120
            ];

            //var_dump($horarioNexti);exit;
            // exit;
            
            $ret = $this->enviaHorarioAtualizar($horario->IDEXTERNO,$horarioNexti, $horario->ID);
        }
        $this->info("Total de {$nHorarios} horarios(s) atualizados!");
    }


    private function enviaHorarioAtualizar($externalId, $horario, $idHorario) {
        $this->info("Atualizando o horario {$externalId} via API Nexti");
        
        $endpoint = "shifts/externalId/{$externalId}";
        // $endpoint = "shifts/{$idHorario}";

        // var_dump($externalId);
        // var_dump(json_encode($horario));exit;
        $response = $this->restClient->put($endpoint, [], [
            'json' => $horario
        ])->getResponse();

        //print_r($response);

        if (!$this->restClient->isResponseStatusCode(200)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o horario {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);

        } else {
            $this->info("Horario {$externalId} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $horarioInserido = $responseData['value'];
            $id = $horarioInserido['id'];
            $this->atualizaRegistro($externalId, 1, $id);
            //$this->atualizaMarcacao($codHor);
        }
    }

    // private function atualizaMarcacao($codHor) {
    //     DB::connection('sqlsrv')->statement("
    //         UPDATE ".env('DB_OWNER')."FLEX_HORARIO_MARCACAO SET 
    //         ".env('DB_OWNER')."FLEX_HORARIO_MARCACAO.SITUACAO = 1
    //         WHERE ".env('DB_OWNER')."FLEX_HORARIO_MARCACAO.CODHOR = '{$codHor}'");
    // }
    
    private function buscaHorarios($page = 0)
    {
        $response = $this->restClient->get("shifts/all", [
            'page' => $page,
            'size' => 10000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar horarios: {$errors}");
        } else {
            $responseData = $this->restClient->getResponseData();
            if ($responseData) {
                $content = $responseData['content'];
                if ($page == 0) {
                    $this->info("Armazendo {$responseData['totalElements']} registros...");
                }

                $this->info("API Nexti retornou os resultados: Pagina {$page}.");
                if (sizeof($content) > 0) {
                    foreach ($content as $reg) {
                        usleep (1000);
                        $this->addHorario($reg);
                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->buscaHorarios($page + 1);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function addHorario($horario)
    {   
        // $sql =
        //     "INSERT INTO ".env('DB_OWNER')."FLEX_HORARIO_AUX (ID, EXTERNALID, NAME)
        //     VALUES 
        //     ({$horario['id']}, '{$horario['externalId']}', '{$horario['name']}'";

        // DB::connection('sqlsrv')->statement($sql);
        // $this->info("Horario {$horario['id']} foi inserida com sucesso.");

        $response = $this->restClient->delete("shifts/{$horario['id']}", [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao excluir o {$horario['id']}: {$errors}");
        } else {
            $this->info("Exclusão de Horario: {$horario['id']} enviada com sucesso.");
        }
    }

    private function insertLogs($msg, $dataInicio, $dataFim) {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime,'{$dataInicio}',5), convert(datetime,'{$dataFim}',5), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }

    private function enviaHorariosExcluir(){
        $horarios = $this->getHorariosExcluir();

        foreach($horarios as $horario){
            $this->enviaHorarioExcluir($horario->ID, $horario->IDEXTERNO);
        }
    }

    private function enviaHorarioExcluir($idHorario, $externalId) {
        $this->info("Atualizando o horario {$externalId} via API Nexti");
        
        $endpoint = "shifts/{$idHorario}";

        $response = $this->restClient->delete($endpoint, [], ['json'=>['active' => false]])->getResponse();

        //print_r($response);

        if (!$this->restClient->isResponseStatusCode(200)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao excluido o horario {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);

        } else {
            $this->info("Horario {$externalId} excluirido com sucesso na API Nexti");

            $this->atualizaRegistro($externalId, 1, $idHorario);
        }
    }

    private function updateHorarioJaExiste(){
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_HORARIO
            SET ".env('DB_OWNER')."FLEX_HORARIO.TIPO = 1
            WHERE ".env('DB_OWNER')."FLEX_HORARIO.SITUACAO IN(0,2)
            AND ".env('DB_OWNER')."FLEX_HORARIO.OBSERVACAO LIKE '%Já existe um registro salvo para o campo externalId com valor%'
            AND ".env('DB_OWNER')."FLEX_HORARIO.TIPO = 0
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

        $this->updateHorarioJaExiste();
        $this->enviaHorariosAtualizar();
        $this->enviaHorariosNovos();
        //$this->buscaHorarios();
    }
}
