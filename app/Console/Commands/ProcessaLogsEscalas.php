<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer\PHPMailer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProcessaLogsEscalas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaLogsEscalas {--test}';

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

        $mail->Subject      = "MAXIPARK - Troca de Escalas - " . date("d/m/Y");

        //TROCA DE ESCALAS
        $sql = 
        "
            SELECT 
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP,
            ".env('DB_OWNER')."FLEX_COLABORADOR.CODFIL,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NOMFUN,
            ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.DATALT,
            ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.ESCALA,
            ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.TURMA,
            ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.TIPO,
            ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.SITUACAO,
            ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.OBSERVACAO
            FROM ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR
                ON ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP = ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.NUMEMP
                AND ".env('DB_OWNER')."FLEX_COLABORADOR.TIPCOL = ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.TIPCOL
                AND ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD = ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.NUMCAD
            WHERE ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.SITUACAO IN(0,2)

        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $trocaEscalas[] = [
                    'EMPRESA'        => $item->NUMEMP,
                    'FILIAL'        => $item->CODFIL,
                    'MATRICULA'        => $item->NUMCAD,
                    'NOME'        => $item->NOMFUN,
                    'DATA'        => date('d/m/Y', strtotime($item->DATALT)),
                    'ESCALA'        => $item->ESCALA,
                    'TURMA'        => $item->TURMA,
                    'TIPO'          => $item->TIPO,
                    'SITUACAO'      => $item->SITUACAO,
                    'OBSERVACAO'    => $item->OBSERVACAO
                ];
            }
        }

        //TROCA DE ESCALAS
        $sql = 
        "
            SELECT 
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP,
            ".env('DB_OWNER')."FLEX_COLABORADOR.CODFIL,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NOMFUN,
            ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.TRANSFERDATETIME,
            ".env('DB_OWNER')."FLEX_ESCALA.CODESC,
            ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.ROTATIONCODE,
            ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.TIPO,
            ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.SITUACAO,
            ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.OBSERVACAO
            FROM ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS
            JOIN ".env('DB_OWNER')."FLEX_ESCALA
                ON ".env('DB_OWNER')."FLEX_ESCALA.ID = ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.SCHEDULEID
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR
                ON ".env('DB_OWNER')."FLEX_COLABORADOR.ID = ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.PERSONID
            WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.SITUACAO IN(0,2)

        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $retTrocaEscalas[] = [
                    'EMPRESA' => $item->NUMEMP,
                    'FILIAL' => $item->CODFIL,
                    'MATRICULA' => $item->NUMCAD,
                    'NOME' => $item->NOMFUN,
                    'DATA' => date('d/m/Y', strtotime($item->TRANSFERDATETIME)),
                    'ESCALA' => $item->CODESC,
                    'TURMA' => $item->ROTATIONCODE,
                    'TIPO' => $item->TIPO,
                    'SITUACAO' => $item->SITUACAO,
                    'OBSERVACAO' => $item->OBSERVACAO
                ];
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
            'ausências' => $ausencias,
            'troca_postos' => $trocaPostos,
            'troca_escalas' => $trocaEscalas,
            'retorno_troca_escalas' => $retTrocaEscalas,
        ];

        $dataUltimoDisparo = $this->verificaDisparoLog();

        $mensagens = [
            "cargos"                => [
                'Segue erros dos cadastros de cargos que deverão',
                'ser enviados do sistema de folha para o nexti',
                ''
            ],
            "horários"              => [
                'Segue erros dos cadastros de horários que deverão',
                'ser enviados do sistema de folha para o nexti',
                ''
            ],
            "escalas"               => [
                'Segue erros dos cadastros de escalas que deverão',
                'ser enviados do sistema de folha para o nexti',
                ''
            ],
            "situações"             => [
                'Segue erros dos cadastros de situações que deverão',
                'ser enviados do sistema de folha para o nexti',
                ''
            ],
            "clientes"             => [
                'Segue erros dos cadastros de clientes que deverão',
                'ser enviados do sistema de folha para o nexti',
                ''
            ],
            "postos"                => [
                'Segue erros dos cadastros de postos que deverão',
                'ser enviados do sistema de folha para o nexti',
                ''
            ],
            "sindicatos"            => [
                'Segue erros dos cadastros de sindicatos que deverão',
                'ser enviados do sistema de folha para o nexti',
                ''
            ],
            "unidades"              => [
                'Segue erros dos cadastros de unidades que deverão',
                'ser enviados do sistema de folha para o nexti',
                ''
            ],
            "áreas"                 => [
                'Segue erros dos cadastros de áreas que deverão',
                'ser enviados do sistema de folha para o nexti',
                ''
            ],
            "troca_empresas"         => [
                'Segue erros das trocas de empresas dos colaboradores',
                '',
                ''
            ],
            "colaboradores"         => [
                'Segue erros dos cadastros de colaboradores que deverão',
                'ser enviados do sistema de folha para o nexti',
                ''
            ],
            "troca_escalas"          => [
                'Segue erros das trocas de escalas dos colaboradores',
                'ocorridas no sistema da folha e que deverão ser',
                'processadas no Nexti'
            ],
            "troca_postos"           => [
                'Segue erros das trocas de postos dos colaboradores',
                'ocorridas no sistema da folha e que deverão ser',
                'processadas no Nexti'
            ],
            "ausências"             => [
                'Segue erros dos afastamentos dos colaboradores',
                'ocorridas no sistema da folha e que deverão ser',
                'processadas no Nexti'
            ],
            "retorno_troca_ausências"     => [
                'Segue erros dos afastamentos dos colaboradores',
                'ocorridas na Nexti e que deverão ser',
                'processadas no sistema de folha'
            ],
            "retorno_troca_escalas"       => [
                'Segue erros das trocas de escalas dos colaboradores',
                'ocorridas na Nexti e que deverão ser',
                'processadas no sistema de folha'
            ],
            "retorno_troca_postos"        => [
                'Segue erros das trocas de postos dos colaboradores',
                'ocorridas na Nexti e que deverão ser',
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
            $sheet->setTitle('Histórico de Troca de Escalas');

            $sheet->setCellValue('A1', 'FLUXO');
            $sheet->setCellValue('B1', 'EMPRESA');
            $sheet->setCellValue('C1', 'FILIAL');
            $sheet->setCellValue('D1', 'MATRICULA');
            $sheet->setCellValue('E1', 'NOME');
            $sheet->setCellValue('F1', 'DATA');
            $sheet->setCellValue('G1', 'ESCALA');
            $sheet->setCellValue('H1', 'TURMA');
            $sheet->setCellValue('I1', 'TIPO');
            $sheet->setCellValue('J1', 'SITUACAO');
            $sheet->setCellValue('K1', 'DATA_OBSERVACAO');
            $sheet->setCellValue('L1', 'OBSERVACAO');


            $rowNumber = 2;

            // REGISTRO DE FLEX_TROCA_ESCALAS_PROTHEUS

            foreach($trocaEscalas as $row){

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
                $sheet->setCellValue('C' . $rowNumber, $row['FILIAL']);
                $sheet->setCellValue('D' . $rowNumber, $row['MATRICULA']);
                $sheet->setCellValue('E' . $rowNumber, $row['NOME']);
                $sheet->setCellValue('F' . $rowNumber, $row['DATA']);
                $sheet->setCellValue('G' . $rowNumber, $row['ESCALA']);
                $sheet->setCellValue('H' . $rowNumber, $row['TURMA']);
                $sheet->setCellValue('I' . $rowNumber, $tipoTexto);
                $sheet->setCellValue('J' . $rowNumber, $situacaoTexto);
                $sheet->setCellValue('K' . $rowNumber, substr($row['OBSERVACAO'], 0, 20));
                $sheet->setCellValue('L' . $rowNumber, substr($row['OBSERVACAO'], 22, 5000));
                $rowNumber++;
            }

            // REGISTRO DE FLEX_RET_AUSENCIAS

                foreach($retTrocaEscalas as $row){

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
                $sheet->setCellValue('C' . $rowNumber, $row['FILIAL']);
                $sheet->setCellValue('D' . $rowNumber, $row['MATRICULA']);
                $sheet->setCellValue('E' . $rowNumber, $row['NOME']);
                $sheet->setCellValue('F' . $rowNumber, $row['DATA']);
                $sheet->setCellValue('G' . $rowNumber, $row['ESCALA']);
                $sheet->setCellValue('H' . $rowNumber, $row['TURMA']);
                $sheet->setCellValue('I' . $rowNumber, $tipoTexto);
                $sheet->setCellValue('J' . $rowNumber, $situacaoTexto);
                $sheet->setCellValue('K' . $rowNumber, substr($row['OBSERVACAO'], 0, 20));
                $sheet->setCellValue('L' . $rowNumber, substr($row['OBSERVACAO'], 22, 5000));
                $rowNumber++;
            }

            $sheet->setAutoFilter('A1:K' . ($rowNumber - 1));

            // TABELA DE ESCALAS

            $escalasSheet = $spreadsheet->createSheet();
            $escalasSheet->setTitle('Cadastro de Escalas');

            $escalasSheet->setCellValue('A1', 'ESCALA');
            $escalasSheet->setCellValue('B1', 'NOME');
            $escalasSheet->setCellValue('C1', 'TIPO');
            $escalasSheet->setCellValue('D1', 'SITUACAO');
            $escalasSheet->setCellValue('E1', 'DATA_OBSERVACAO');
            $escalasSheet->setCellValue('F1', 'OBSERVACAO');

            foreach($escalas as $row){

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

                $sheet->setCellValue('A' . $rowNumber, $row['ESCALA']);
                $sheet->setCellValue('B' . $rowNumber, $row['NOME']);
                $sheet->setCellValue('C' . $rowNumber, $row['TIPO']);
                $sheet->setCellValue('D' . $rowNumber, $row['SITUACAO']);
                $sheet->setCellValue('E' . $rowNumber, substr($row['OBSERVACAO'], 0, 20));
                $sheet->setCellValue('F' . $rowNumber, substr($row['OBSERVACAO'], 22, 5000));
                $rowNumber++;
            }

            $writer = new Xlsx($spreadsheet);
            $arquivo = $writer->save('PROCESSO_TROCA_DE_ESCALAS' . date('d-m-Y') . '.xlsx');
            $mail->addAttachment('PROCESSO_TROCA_DE_ESCALAS' . date('d-m-Y') . '.xlsx', 'PROCESSO_TROCA_DE_ESCALAS' . date('d-m-Y') . '.xlsx');

            $mail->AddAddress(env('EMAIL_LOG'));
            if ($possuiRegistro) {
                $mail->AddAddress('parcerias@flexsystems.com.br');                     
            }

            if (!$mail->Send()) {
                $this->error("Erro: {$mail->ErrorInfo}");
            }else{
                $this->insertDisparoLog(date('Y-m-d H:i:s'));
            }
            unlink('PROCESSO_TROCA_DE_ESCALAS' . date('d-m-Y') . '.xlsx');
        }
    }

    private function insertDisparoLog($dataInicio) {
        $sql =
        "
            UPDATE ".env('DB_OWNER')."FLEX_DATA_LOG_EMAIL
            SET DATA_INICIO = CONVERT(DATETIME, '{$dataInicio}', 120)
            WHERE ID = 2
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
            WHERE ID  = 2
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
