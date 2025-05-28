<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer\PHPMailer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProcessaLogsAusencias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaLogsAusencias {--test}';

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

        $mail->Subject      = "MAXIPARK - Ausencias - " . date("d/m/Y");

    
        // AUSÊNCIAS
        $sql = 
        "
            SELECT 
                ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.NUMEMP as EMPRESA,
                ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.NUMCAD AS MATRICULA,
                ".env('DB_OWNER')."FLEX_COLABORADOR.NOMFUN AS COLABORADOR,
                ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.absenceSituationExternalId,
                ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.startDateTime AS DATA_INICIAL,
                ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.startMinute AS HORA_INICIAL,
                ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.finishDateTime AS DATA_FINAL,
                ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.finishMinute AS HORA_FINAL,
                ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO,
                ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO,
                ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.OBSERVACAO
            FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
            INNER JOIN ".env('DB_OWNER')."FLEX_COLABORADOR
                ON (".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.NUMEMP
                AND ".env('DB_OWNER')."FLEX_COLABORADOR.TIPCOL = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPCOL
                AND ".env('DB_OWNER')."FLEX_COLABORADOR.numcad = ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.NUMCAD)
            WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO IN (0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $ausencias[] = [
                    'EMPRESA'           => $item->EMPRESA,
                    'MATRICULA'         => $item->MATRICULA,
                    'COLABORADOR'       => $item->COLABORADOR,
                    'SITUACAO DA AUSENCIA'          => $item->absenceSituationExternalId,
                    'DATA_INICIAL'      => date('d/m/Y', strtotime($item->DATA_INICIAL)),
                    'HORA_INICIAL'      => $item->HORA_INICIAL > 0 ? $this->hora($item->HORA_INICIAL) : 0,
                    'DATA_FINAL'        => date('d/m/Y', strtotime($item->DATA_FINAL)),
                    'HORA_FINAL'        => $item->HORA_FINAL > 0 ? $this->hora($item->HORA_FINAL) : 0,
                    'TIPO'              => $item->TIPO,
                    'SITUACAO'          => $item->SITUACAO,
                    'OBSERVACAO'        => $item->OBSERVACAO
                ];
            }
        }

        // RETORNO AUSÊNCIAS
        $sql = 
        "
            SELECT 
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP,
            ".env('DB_OWNER')."FLEX_COLABORADOR.CODFIL,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NOMFUN,
            ".env('DB_OWNER')."FLEX_SITUACAO.CODSIT,
            ".env('DB_OWNER')."FLEX_SITUACAO.DESSIT,
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME,
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTMINUTE,
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME,
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHMINUTE,
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO,
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.SITUACAO,
            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.OBSERVACAO
            FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR
                ON ".env('DB_OWNER')."FLEX_COLABORADOR.ID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONID
            LEFT JOIN ".env('DB_OWNER')."FLEX_SITUACAO
                ON ".env('DB_OWNER')."FLEX_SITUACAO.IDEXTERNO = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID
            WHERE ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.SITUACAO IN(0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $retTrocaAusencias[] = [
                    'EMPRESA' => $item->NUMEMP,
                    'FILIAL' => $item->CODFIL,
                    'MATRICULA' => $item->NUMCAD,
                    'COLABORADOR' => $item->NOMFUN,
                    'SITUACAO DA AUSENCIA' => $item->CODSIT,
                    'DATA_INICIAL' => date('d/m/Y', strtotime($item->STARTDATETIME)),
                    'HORA_INICIAL' => $item->STARTMINUTE > 0 ? $this->hora($item->STARTMINUTE) : 0,
                    'DATA_FINAL' => date('d/m/Y', strtotime($item->FINISHDATETIME)),
                    'HORA_FINAL' => $item->FINISHMINUTE > 0 ? $this->hora($item->FINISHMINUTE) : 0,
                    'TIPO' => $item->TIPO,
                    'SITUACAO' => $item->SITUACAO,
                    'OBSERVACAO' => $item->OBSERVACAO
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
            'colaboradores' => $colaboradores,
            'troca_sindicatos' => $trocaSindicatos,
            'ausencias' => $ausencias,
            'troca_postos' => $trocaPostos,
            'troca_escalas' => $trocaEscalas,
            'retorno_troca_ausencias' => $retTrocaAusencias,
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
            "ausencias"             => [
                'Segue erros dos afastamentos dos colaboradores',
                'ocorridas no sistema da folha e que deverão ser',
                'processadas no NEXTI'
            ],
            "retorno_troca_ausencias"     => [
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
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Histórico de Ausências');

            $sheet->setCellValue('A1', 'FLUXO');
            $sheet->setCellValue('B1', 'EMPRESA');
            $sheet->setCellValue('C1', 'MATRICULA');
            $sheet->setCellValue('D1', 'COLABORADOR');
            $sheet->setCellValue('E1', 'SITUACAO DA AUSENCIA');
            $sheet->setCellValue('F1', 'DATA_INICIAL');
            $sheet->setCellValue('G1', 'DATA_FINAL');
            $sheet->setCellValue('H1', 'TIPO');
            $sheet->setCellValue('I1', 'SITUACAO');
            $sheet->setCellValue('J1', 'DATA_OBSERVACAO');
            $sheet->setCellValue('K1', 'OBSERVACAO');

            $rowNumber = 2;

            // REGISTRO DE FLEX_AUSENCIAS_PROTHEUS

            foreach($ausencias as $row){

                $tipoTexto = '';
                switch ($row['TIPO']){
                    case 0:
                        $tipoTexto = 'INSERIR';
                        break;
                    case 1:
                        $tipoTexto = 'ATUALIZAR';
                        break;
                    case 3:
                        $tipoTexto = 'EXCLUIR';
                        break;        
                }

                $situacaoTexto = '';
                switch ($row['SITUACAO']){
                    case 0:
                        $situacaoTexto = 'Pendente de Processamento';
                        break;
                    case 2:
                        $situacaoTexto = 'Erro de Processamento';
                        break;    
                }

                $sheet->setCellValue('A' . $rowNumber, 'Protheus para Nexti');
                $sheet->setCellValue('B' . $rowNumber, $row['EMPRESA']);
                $sheet->setCellValue('C' . $rowNumber, $row['MATRICULA']);
                $sheet->setCellValue('D' . $rowNumber, $row['COLABORADOR']);
                $sheet->setCellValue('E' . $rowNumber, $row['SITUACAO DA AUSENCIA']);
                $sheet->setCellValue('F' . $rowNumber, $row['DATA_INICIAL']);
                $sheet->setCellValue('G' . $rowNumber, $row['DATA_FINAL']);
                $sheet->setCellValue('H' . $rowNumber, $tipoTexto);
                $sheet->setCellValue('I' . $rowNumber, $situacaoTexto);
                $sheet->setCellValue('J' . $rowNumber, substr($row['OBSERVACAO'], 0, 20));
                $sheet->setCellValue('K' . $rowNumber, substr($row['OBSERVACAO'], 22, 5000));
                $rowNumber++;
            }

            // REGISTRO DE FLEX_RET_AUSENCIAS

                foreach($retTrocaAusencias as $row){

                $tipoTexto = '';
                switch ($row['TIPO']){
                    case 0:
                        $tipoTexto = 'INSERIR';
                        break;
                    case 1:
                        $tipoTexto = 'ATUALIZAR';
                        break;
                    case 3:
                        $tipoTexto = 'EXCLUIR';
                        break;        
                }

                $situacaoTexto = '';
                switch ($row['SITUACAO']){
                    case 0:
                        $situacaoTexto = 'Pendente de Processamento';
                        break;
                    case 2:
                        $situacaoTexto = 'Erro de Processamento';
                        break;    
                }

                $sheet->setCellValue('A' . $rowNumber, 'Nexti para Protheus');
                $sheet->setCellValue('B' . $rowNumber, $row['EMPRESA']);
                $sheet->setCellValue('C' . $rowNumber, $row['MATRICULA']);
                $sheet->setCellValue('D' . $rowNumber, $row['COLABORADOR']);
                $sheet->setCellValue('E' . $rowNumber, $row['SITUACAO DA AUSENCIA']);
                $sheet->setCellValue('F' . $rowNumber, $row['DATA_INICIAL']);
                $sheet->setCellValue('G' . $rowNumber, $row['DATA_FINAL']);
                $sheet->setCellValue('H' . $rowNumber, $tipoTexto);
                $sheet->setCellValue('I' . $rowNumber, $situacaoTexto);
                $sheet->setCellValue('J' . $rowNumber, substr($row['OBSERVACAO'], 0, 20));
                $sheet->setCellValue('K' . $rowNumber, substr($row['OBSERVACAO'], 22, 5000));
                $rowNumber++;
            }

            $sheet->setAutoFilter('A1:J' . ($rowNumber - 1));

            $writer = new Xlsx($spreadsheet);
            $arquivo = $writer->save('ENVIO_DE_AUSENCIAS' . date('d-m-Y') . '.xlsx');
            $mail->addAttachment('ENVIO_DE_AUSENCIAS' . date('d-m-Y') . '.xlsx', 'ENVIO_DE_AUSENCIAS' . date('d-m-Y') . '.xlsx');

            $mail->AddAddress(env('EMAIL_LOG'));
            if ($possuiRegistro) {
                $mail->AddAddress('parcerias@flexsystems.com.br');
                           
            }  

            if (!$mail->Send()) {
                $this->error("Erro: {$mail->ErrorInfo}");
            }else{
                $this->insertDisparoLog(date('Y-m-d H:i:s'));
            }
            unlink('ENVIO_DE_AUSENCIAS' . date('d-m-Y') . '.xlsx');
        }
    }

    private function insertDisparoLog($dataInicio) {
        $sql =
        "
            UPDATE ".env('DB_OWNER')."FLEX_DATA_LOG_EMAIL
            SET DATA_INICIO = CONVERT(DATETIME, '{$dataInicio}', 120)
            WHERE ID = 3
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
            WHERE ID  = 3
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