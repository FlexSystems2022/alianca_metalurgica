<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer\PHPMailer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProcessaLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaLogs {--test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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

    private function enviaEmailLog(): void
    {
        $retorno = [];
        $cargos = [];
        $horarios = [];
        $escalas = [];
        $escalasOld = [];
        $situacoes = [];
        $tomadores = [];
        $postos = [];
        $sindicatos = [];
        $unidades = [];
        $areas = [];
        $trocaEmpresas = [];
        $colaboradores = [];
        $trocaEscalas = [];
        $trocaPostos = [];
        $trocaSindicatos = [];
        $trocaHorarios = [];
        $ausencias = [];
        $feriados = [];
        $retTrocaAusencias = [];
        $retTrocaEscalas = [];
        $retTrocaPostos = [];
        $retTrocaSindicatos = [];

        $mail = new PHPMailer();
        // $mail->SMTPDebug = 2;
        // $mail->Debugoutput = 'html';
        $mail->Port       = env('MAIL_PORT');
        $mail->SMTPSecure = 'tls';
        $mail->IsSMTP();
        $mail->Host       = env('MAIL_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('MAIL_USERNAME');
        $mail->Password   = env('MAIL_PASSWORD');
        $mail->From       = env('MAIL_USERNAME');
        $mail->CharSet    = 'utf-8';
        $mail->FromName   = env('MAIL_FROM_NAME');

        $possuiRegistro = false;

        $mail->WordWrap     = 50;
        $mail->IsHTML(true);
        $mail->Body         = 
        '
            Identificamos alguns registros que estão apresentando dificuldades na integração. Precisamos que sejam analisados e se possível corrigidos o quanto antes para garantir que o processo siga sem interrupções. Fique tranquilo, pois assim que corrido a aplicação irá conseguir transmitir a informação, mas se mesmo assim o erro continuar ou você não souber o que fazer basta acionar nosso suporte pelo e-mail suporte@flexsystems.com.br Atenciosamente, Equipe Flex Systems
        ';

        $mail->Subject      = "ALIANÇA - Cadastros - " . date("d/m/Y");

        // CARGOS
        $sql = 
        "
            SELECT 
                ".env('DB_OWNER')."FLEX_CARGO.ESTCAR,
                ".env('DB_OWNER')."FLEX_CARGO.CODCAR,
                ".env('DB_OWNER')."FLEX_CARGO.TITCAR,
                ".env('DB_OWNER')."FLEX_CARGO.OBSERVACAO
            FROM ".env('DB_OWNER')."FLEX_CARGO 
            WHERE ".env('DB_OWNER')."FLEX_CARGO.SITUACAO IN (0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $cargos[] = [
                    'ESTCAT'        => $item->ESTCAR,
                    'CODCAR'        => $item->CODCAR,
                    'TITCAR'        => $item->TITCAR,
                    'TIPO'          => $item->TIPO,
                    'SITUACAO'      => $item->SITUACAO,
                    'OBSERVACAO'    => $item->OBSERVACAO
                ];
            }
        }

        // TOMADORES
        $sql =
        "
            SELECT
                ".env('DB_OWNER')."FLEX_CLIENTE.CODOEM AS CLIENTE,
                ".env('DB_OWNER')."FLEX_CLIENTE.NOMOEM AS NOME,
                ".env('DB_OWNER')."FLEX_CLIENTE.TIPO,
                ".env('DB_OWNER')."FLEX_CLIENTE.SITUACAO,
                ".env('DB_OWNER')."FLEX_CLIENTE.OBSERVACAO
            FROM ".env('DB_OWNER')."FLEX_CLIENTE
            WHERE ".env('DB_OWNER')."FLEX_CLIENTE.SITUACAO IN (0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $tomadores[] = [
                    'CLIENTE'       => $item->CLIENTE,
                    'NOME'          => $item->NOME,
                    'TIPO'          => $item->TIPO,
                    'SITUACAO'      => $item->SITUACAO,
                    'OBSERVACAO'    => $item->OBSERVACAO
                ];
            }
        }

        // UNIDADES
        $sql =
        "
            SELECT 
                * 
            FROM ".env('DB_OWNER')."FLEX_UNIDADE 
            WHERE ".env('DB_OWNER')."FLEX_UNIDADE.SITUACAO IN(0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $unidade) {
                $unidades[] = [
                    'UNIDADE'   => $unidade->IDEXTERNO . ' - ' . $unidade->DESPOS,
                    'TIPO'          => $unidade->TIPO,
                    'SITUACAO'      => $unidade->SITUACAO,
                    'OBSERVACAO'  => $unidade->OBSERVACAO
                ];
            }
        }

        // SITUAÇÕES
        $sql =
        "
            SELECT
                ".env('DB_OWNER')."FLEX_SITUACAO.CODSIT AS CODIGO_SITUACAO,
                ".env('DB_OWNER')."FLEX_SITUACAO.DESSIT AS NOME,
                ".env('DB_OWNER')."FLEX_SITUACAO.TIPO,
                ".env('DB_OWNER')."FLEX_SITUACAO.SITUACAO,
                ".env('DB_OWNER')."FLEX_SITUACAO.OBSERVACAO
            FROM ".env('DB_OWNER')."FLEX_SITUACAO
            WHERE ".env('DB_OWNER')."FLEX_SITUACAO.SITUACAO IN (0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $situacoes[] = [
                    'CODIGO DA SITUACAO'      => $item->CODIGO_SITUACAO,
                    'NOME'          => $item->NOME,
                    'TIPO'          => $item->TIPO,
                    'SITUACAO'      => $item->SITUACAO,
                    'OBSERVACAO'    => $item->OBSERVACAO
                ];
            }
        }

        // TROCA DE EMPRESAS
        $sql =
        "
            SELECT 
                * 
            FROM ".env('DB_OWNER')."FLEX_TROCA_EMPRESA 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.SITUACAO IN(0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $troca) {
                $trocaEmpresas[] = [
                    'NUMEMP'        => $troca->NUMEMP,
                    'TIPCOL'        => $troca->TIPCOL,
                    'NUMCAD'        => $troca->NUMCAD,
                    'DATA'          => date('d/m/Y', strtotime($troca->DATALT)),
                    'TIPO'          => $troca->TIPO,
                    'SITUACAO'      => $troca->SITUACAO,
                    'OBSERVACAO'    => $troca->OBSERVACAO
                ];
            }
        }

        // FLEX_SINDICATOS
        $sql =
        "
            SELECT 
                * 
            FROM ".env('DB_OWNER')."FLEX_SINDICATOS 
            WHERE ".env('DB_OWNER')."FLEX_SINDICATOS.SITUACAO IN(0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $sindicato) {
                $sindicatos[] = [
                    'SINDICATO'   => $sindicato->CODSIN . ' - ' . $sindicato->NOMSIN,
                    'TIPO'          => $sindicato->TIPO,
                    'SITUACAO'      => $sindicato->SITUACAO,
                    'OBSERVACAO'  => $sindicato->OBSERVACAO
                ];
            }
        }

        //HORÁRIOS
        $sql = 
        "
            SELECT
                ".env('DB_OWNER')."FLEX_HORARIO.CODHOR AS HORARIO,
                ".env('DB_OWNER')."FLEX_HORARIO.DESHOR AS NOME,
                ".env('DB_OWNER')."FLEX_HORARIO.OBSERVACAO,
                ".env('DB_OWNER')."FLEX_HORARIO.TIPO,
                ".env('DB_OWNER')."FLEX_HORARIO.SITUACAO
            FROM ".env('DB_OWNER')."FLEX_HORARIO
            WHERE ".env('DB_OWNER')."FLEX_HORARIO.SITUACAO IN (0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $horarios[] = array(
                    'HORARIO'       => $item->HORARIO,
                    'NOME'          => $item->NOME,
                    'TIPO'          => $item->TIPO,
                    'SITUACAO'      => $item->SITUACAO,
                    'OBSERVACAO'    => $item->OBSERVACAO
                );
            }
        }

        // ESCALAS
        $sql =
        "
            SELECT
                ".env('DB_OWNER')."FLEX_ESCALA.CODESC AS ESCALA,
                ".env('DB_OWNER')."FLEX_ESCALA.NOMESC AS NOME,
                ".env('DB_OWNER')."FLEX_ESCALA.OBSERVACAO,
                ".env('DB_OWNER')."FLEX_ESCALA.TIPO,
                ".env('DB_OWNER')."FLEX_ESCALA.SITUACAO
            FROM ".env('DB_OWNER')."FLEX_ESCALA
            WHERE ".env('DB_OWNER')."FLEX_ESCALA.SITUACAO IN (0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $escalas[] = array(
                    'ESCALA'        => $item->ESCALA,
                    'NOME'          => $item->NOME,
                    'TIPO'          => $item->TIPO,
                    'SITUACAO'      => $item->SITUACAO,
                    'OBSERVACAO'    => $item->OBSERVACAO
                );
            }
        }

        // ESCALAS ANTIGAS
        $sql =
        "
            SELECT
                ".env('DB_OWNER')."FLEX_ESCALA.CODESC AS ESCALA,
                ".env('DB_OWNER')."FLEX_ESCALA.NOMESC AS NOME,
                ".env('DB_OWNER')."FLEX_ESCALA.OBSERVACAO,
                ".env('DB_OWNER')."FLEX_ESCALA.TIPO,
                ".env('DB_OWNER')."FLEX_ESCALA.SITUACAO
            FROM ".env('DB_OWNER')."FLEX_ESCALA
            WHERE ".env('DB_OWNER')."FLEX_ESCALA.SITUACAO IN (0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $escalasOld[] = array(
                    'ESCALA'        => $item->ESCALA,
                    'NOME'          => $item->NOME,
                    'TIPO'          => $item->TIPO,
                    'SITUACAO'      => $item->SITUACAO,
                    'OBSERVACAO'    => $item->OBSERVACAO
                );
            }
        }

        //COLABORADORES
        $sql =
        "SELECT 
            ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO AS COLAB,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NOMFUN AS NOME,
            ".env('DB_OWNER')."FLEX_COLABORADOR.TIPO,
            ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO,
            ".env('DB_OWNER')."FLEX_COLABORADOR.OBSERVACAO
        FROM ".env('DB_OWNER')."FLEX_COLABORADOR 
        WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO IN (0,2)";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $colaboradores[] = [
                    'COLAB'         => $item->COLAB,
                    'NOME'          => $item->NOME,
                    'TIPO'          => $item->TIPO,
                    'SITUACAO'      => $item->SITUACAO,
                    'OBSERVACAO'    => $item->OBSERVACAO
                ];
            }
        }

        //TROCA DE SINDICATOS
        $sql = 
        "
            SELECT 
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NOMFUN,
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.CODSIN,
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.DATALT,
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.TIPO,
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO,
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.OBSERVACAO
            FROM ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR
                ON dbo.FLEX_COLABORADOR.IDEXTERNO = ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.IDEXTERNO
            WHERE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO IN(0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $trocaSindicatos[] = [
                    'NUMEMP'        => $item->NUMEMP,
                    'NUMCAD'        => $item->NUMCAD,
                    'NOMFUN'        => $item->NOMFUN,
                    'CODSIN'        => $item->CODSIN,
                    'DATALT'        => date('d/m/Y', strtotime($item->DATALT)),
                    'TIPO'          => $item->TIPO,
                    'SITUACAO'      => $item->SITUACAO,
                    'OBSERVACAO'    => $item->OBSERVACAO
                ];
            }
        }

        $retorno = [
            'cargos' => $cargos,
            'clientes' => $tomadores,
            'unidades' => $unidades,
            'postos' => $postos,
            'situações' => $situacoes,
            'troca_empresas' => $trocaEmpresas,
            'sindicatos' => $sindicatos,
            'horários' => $horarios,
            'escalas' => $escalas,
            'escalas_antigas' => $escalasOld,
            'colaboradores' => $colaboradores,
            'troca_sindicatos' => $trocaSindicatos,
            'ausências' => $ausencias,
            'troca_postos' => $trocaPostos,
            'troca_escalas' => $trocaEscalas,
        ];

        $dataUltimoDisparo = $this->verificaDisparoLog();

        $mensagens = [
            "cargos"                => [
                'Segue erros dos cadastros de cargos que deverão',
                'ser enviados do sistema de folha para o NEXTI',
                ''
            ],
            "horários"              => [
                'Segue erros dos cadastros de horários que deverão',
                'ser enviados do sistema de folha para o NEXTI',
                ''
            ],
            "escalas"               => [
                'Segue erros dos cadastros de escalas que deverão',
                'ser enviados do sistema de folha para o NEXTI',
                ''
            ],
            "situações"             => [
                'Segue erros dos cadastros de situações que deverão',
                'ser enviados do sistema de folha para o NEXTI',
                ''
            ],
            "clientes"             => [
                'Segue erros dos cadastros de clientes que deverão',
                'ser enviados do sistema de folha para o NEXTI',
                ''
            ],
            "postos"                => [
                'Segue erros dos cadastros de postos que deverão',
                'ser enviados do sistema de folha para o NEXTI',
                ''
            ],
            "sindicatos"            => [
                'Segue erros dos cadastros de sindicatos que deverão',
                'ser enviados do sistema de folha para o NEXTI',
                ''
            ],
            "unidades"              => [
                'Segue erros dos cadastros de unidades que deverão',
                'ser enviados do sistema de folha para o NEXTI',
                ''
            ],
            "áreas"                 => [
                'Segue erros dos cadastros de áreas que deverão',
                'ser enviados do sistema de folha para o NEXTI',
                ''
            ],
            "troca_empresas"         => [
                'Segue erros das trocas de empresas dos colaboradores',
                '',
                ''
            ],
            "colaboradores"         => [
                'Segue erros dos cadastros de colaboradores que deverão',
                'ser enviados do sistema de folha para o NEXTI',
                ''
            ],
            "troca_escalas"          => [
                'Segue erros das trocas de escalas dos colaboradores',
                'ocorridas no sistema da folha e que deverão ser',
                'processadas no NEXTI'
            ],
            "troca_postos"           => [
                'Segue erros das trocas de postos dos colaboradores',
                'ocorridas no sistema da folha e que deverão ser',
                'processadas no NEXTI'
            ],
            "ausências"             => [
                'Segue erros dos afastamentos dos colaboradores',
                'ocorridas no sistema da folha e que deverão ser',
                'processadas no NEXTI'
            ],
            "retorno_troca_ausências"     => [
                'Segue erros dos afastamentos dos colaboradores',
                'ocorridas na NEXTI e que deverão ser',
                'processadas no sistema de folha'
            ],
            "retorno_troca_escalas"       => [
                'Segue erros das trocas de escalas dos colaboradores',
                'ocorridas na NEXTI e que deverão ser',
                'processadas no sistema de folha'
            ],
            "retorno_troca_postos"        => [
                'Segue erros das trocas de postos dos colaboradores',
                'ocorridas na NEXTI e que deverão ser',
                'processadas no sistema de folha'
            ],
            "troca_sindicatos"       => [
                '',
                '',
                ''
            ],
            "retorno_troca_sindicatos"    => [
                '',
                '',
                ''
            ],
            "troca_horários"         => [
                '',
                '',
                ''
            ],
            "feriados"              => [
                '',
                '',
                ''
            ],
        ];

           if($this->verificaDisparoLog()){
            $planilha = new Spreadsheet();
            $planilhaProvisoria = new Spreadsheet();
            $tableSheetIndex = 1;
            foreach ($retorno as $index => $arrayInterno) {
                $aba = $planilhaProvisoria->createSheet($tableSheetIndex);
                $aba->setTitle($index);
                $linhaIndex = 1;
                if($arrayInterno){
                    foreach ($arrayInterno as $linha => $valores) {
                        $colunaIndex = 1;

                        foreach ($valores as $coluna => $valor) {
                            if($linhaIndex == 1){
                                $aba->setCellValueByColumnAndRow($colunaIndex, $linhaIndex, $coluna);
                            }
                            $aba->setCellValueByColumnAndRow($colunaIndex, $linhaIndex + 1, $valor);
                            $colunaIndex ++;
                        }
                        $linhaIndex ++;
                    }
                    $planilha->addSheet($aba, $tableSheetIndex);
                }
                $tableSheetIndex ++;
            }
          $planilha->removeSheetByIndex(0);
            $writer = new Xlsx($planilha);
            $arquivo = $writer->save('ERROS_ALIANÇA_CADASTROS_' . date('d-m-Y') . '.xlsx');
            $mail->addAttachment('ERROS_ALIANÇA_CADASTROS_' . date('d-m-Y') . '.xlsx', 'ERROS_ALIANÇA_CADASTROS_' . date('d-m-Y') . '.xlsx');

            $mail->AddAddress(env('EMAIL_LOG'));
            if ($possuiRegistro) {
                $mail->AddAddress('parcerias@flexsystems.com.br');
                        
            }

            if (!$mail->Send()) {
                $this->error("Erro: {$mail->ErrorInfo}");
            }else{
                $this->insertDisparoLog(date('Y-m-d H:i:s'));
            }
            unlink('ERROS_ALIANÇA_CADASTROS_' . date('d-m-Y') . '.xlsx');
        }
    }

    private function insertDisparoLog($dataInicio) {
        $sql =
        "
            UPDATE ".env('DB_OWNER')."FLEX_DATA_LOG_EMAIL
            SET DATA_INICIO = CONVERT(DATETIME, '{$dataInicio}', 120)
            WHERE ID = 4
        ";

        //var_dump($sql);exit;
        DB::connection('sqlsrv')->statement($sql);
    }

    private function verificaDisparoLog(): bool
    {
        if($this->option('test')) {
            return true;
        }

        $now = \Carbon\Carbon::now();

        if($now->format('H') < 7) {
            return false;
        }

        $sql = "
            SELECT
                DATA_INICIO
            FROM ".env('DB_OWNER')."FLEX_DATA_LOG_EMAIL
            WHERE ID  = 4
        ";

        $log = DB::connection('sqlsrv')->selectOne($sql);
        if(!$log) {
            return true;
        }

        $date = \Carbon\Carbon::parse($log->DATA_INICIO)->setMinute(0)->setSecond(0);
        if($date->isBefore(\Carbon\Carbon::today())) {
            return true;
        }

        if($date->format('H') <= 6) {
            return true;
        }

        if($now->format('H') > 12 && $date->format('H') < 13) {
            return true;
        }

        return false;
    }

    public function hora($minutos)
    {
        $hora = floor($minutos/60);
        $resto = $minutos%60;
        $resto = str_pad($resto, 2, 0, STR_PAD_LEFT);
        return $hora.':'.$resto;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->enviaEmailLog();
    }
}