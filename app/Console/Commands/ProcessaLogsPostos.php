<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer\PHPMailer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProcessaLogsPostos extends Command
{
    protected $signature = 'ProcessaLogsPostos {--test}';

    protected $description = 'Command description';

    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');

        parent::__construct();
    }

    public function handle()
    {
        $this->enviaEmailLog();
    }

    private function dicionarioTipo($tipo)
    {
        $array = [
            0 => 'Inserir',
            1 => 'Atualizar',
            3 => 'Excluir'
        ];

        return $array[$tipo];
    }

    private function dicionarioSituacao($situacao)
    {
        $array = [
            0 => 'Pendente',
            1 => 'Inserido',
            2 => 'Erro'
        ];

        return $array[$situacao];
    }

    private function buscaPostosErros()
    {
        $sql = 
        "
            SELECT 
            ".env('DB_OWNER')."FLEX_POSTO.ESTPOS,
            ".env('DB_OWNER')."FLEX_POSTO.POSTRA,
            '' AS TABORG,
            '' AS NUMLOC,
            ".env('DB_OWNER')."FLEX_POSTO.DESPOS,
            ".env('DB_OWNER')."FLEX_POSTO.TIPO,
            ".env('DB_OWNER')."FLEX_POSTO.SITUACAO,
            ".env('DB_OWNER')."FLEX_POSTO.OBSERVACAO,
            '' AS CODLOC
            FROM ".env('DB_OWNER')."FLEX_POSTO
            WHERE ".env('DB_OWNER')."FLEX_POSTO.SITUACAO IN(0,2)
            --POSTRA = '1.1.010038000.014'
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            foreach ($itens as $item) {
                $possuiRegistro = true;
                $postos[] = [
                    'ESTRUTURA' => $item->ESTPOS,
                    'CODIGO' => $item->POSTRA,
                    'ORGANOGRAMA' => $item->TABORG,
                    'CODIGO LOCAL' => $item->CODLOC,
                    'NOME' => $item->DESPOS,
                    'TIPO' => $this->dicionarioTipo($item->TIPO),
                    'SITUACAO' => $this->dicionarioSituacao($item->SITUACAO),
                    'DATA_OBS' => substr($item->OBSERVACAO, 0, 20),
                    'OBSERVACAO' => substr($item->OBSERVACAO, 22, 5000)
                ];
            }

            return $postos;
        }
    }

    private function buscaTrocaPostosErros()
    {
        $sql = 
        "
            SELECT 
            'Nexti para Protheus' AS FLUXO,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP AS EMPRESA,
            ".env('DB_OWNER')."FLEX_COLABORADOR.CODFIL AS FILIAL,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD AS MATRICULA,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NOMFUN AS NOME,
            ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.TRANSFERDATETIME AS DATA,
            ".env('DB_OWNER')."FLEX_POSTO.POSTRA AS CODIGO_POSTO,
            ".env('DB_OWNER')."FLEX_POSTO.DESPOS AS NOME_POSTO,
            ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.TIPO,
            ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.SITUACAO,
            ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.OBSERVACAO
            FROM ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR
                ON ".env('DB_OWNER')."FLEX_COLABORADOR.ID = ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.PERSONID
            JOIN ".env('DB_OWNER')."FLEX_POSTO
                ON ".env('DB_OWNER')."FLEX_POSTO.ID = ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.WORKPLACEID
            WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.SITUACAO IN(0,2)

            UNION ALL

            SELECT 
            'Protheus para Nexti' AS FLUXO,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP AS EMPRESA,
            ".env('DB_OWNER')."FLEX_COLABORADOR.CODFIL AS FILIAL,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD AS MATRICULA,
            ".env('DB_OWNER')."FLEX_COLABORADOR.NOMFUN AS NOME,
            ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.INIATU AS DATA,
            ".env('DB_OWNER')."FLEX_POSTO.POSTRA AS CODIGO_POSTO,
            ".env('DB_OWNER')."FLEX_POSTO.DESPOS AS NOME_POSTO,
            ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.TIPO,
            ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.SITUACAO,
            ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.OBSERVACAO
            FROM ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS
            JOIN ".env('DB_OWNER')."FLEX_POSTO
                ON ".env('DB_OWNER')."FLEX_POSTO.NUMEMP = ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.NUMEMP
                AND ".env('DB_OWNER')."FLEX_POSTO.ESTPOS = ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.ESTPOS
                AND ".env('DB_OWNER')."FLEX_POSTO.POSTRA = ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.POSTRA
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR
                ON ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP = ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.NUMEMP
                AND ".env('DB_OWNER')."FLEX_COLABORADOR.TIPCOL = ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.TIPCOL
                AND ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD = ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.NUMCAD
            WHERE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.SITUACAO IN(0,2)
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $trocaPostos[] = [
                    'FLUXO' => $item->FLUXO,
                    'EMPRESA' => $item->EMPRESA,
                    'FILIAL' => $item->FILIAL,
                    'MATRICULA' => $item->MATRICULA,
                    'NOME' => $item->NOME,
                    'DATA' => date('d/m/Y', strtotime($item->DATA)),
                    'CODIGO POSTO' => $item->CODIGO_POSTO,
                    'NOME POSTO' => $item->NOME_POSTO,
                    'TIPO' => $this->dicionarioTipo($item->TIPO),
                    'SITUACAO' => $this->dicionarioSituacao($item->SITUACAO),
                    'DATA_OBS' => substr($item->OBSERVACAO, 0, 20),
                    'OBSERVACAO' => substr($item->OBSERVACAO, 22, 5000)
                ];
            }
        }

        return $trocaPostos;
    }

    private function enviaEmailLog(): void
    {
        $retorno = [];
        $postos = [];
        $trocasPostos = [];

        $mail = new PHPMailer();
        $mail->Port = env('MAIL_PORT');
        $mail->SMTPSecure = 'tls';
        $mail->IsSMTP();
        $mail->Host = env('MAIL_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = env('MAIL_USERNAME');
        $mail->Password = env('MAIL_PASSWORD');
        $mail->From = env('MAIL_USERNAME');
        $mail->CharSet = 'utf-8';
        $mail->FromName = env('MAIL_FROM_NAME');
        $possuiRegistro = false;
        $mail->WordWrap = 50;
        $mail->IsHTML(true);
        $mail->Body = 
        '
            Identificamos alguns registros que estão apresentando dificuldades na integração. Precisamos que sejam analisados e se possível corrigidos o quanto antes para garantir que o processo siga sem interrupções. Fique tranquilo, pois assim que corrigido a aplicação irá conseguir transmitir a informação, mas se mesmo assim o erro continuar ou você não souber o que fazer basta acionar nosso suporte pelo e-mail suporte@flexsystems.com.br Atenciosamente, Equipe Flex Systems
        ';

        $mail->Subject = "ALIANÇA - Troca de Postos - " . date("d/m/Y");

        $retorno['Cadastro de Postos'] = $this->buscaPostosErros();
        $retorno['Trocas de Postos'] = $this->buscaTrocaPostosErros();

        if($this->verificaDisparoLog()){
        //if(1==1){
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
                    $aba->setAutoFilter('A1:K' . $linhaIndex);
                    $planilha->addSheet($aba, $tableSheetIndex);
                }
                $tableSheetIndex ++;
            }


            $planilha->removeSheetByIndex(0);
            $writer = new Xlsx($planilha);
            $arquivo = $writer->save('PROCESSO_TROCA_DE_POSTOS' . date('d-m-Y') . '.xlsx');
            $mail->addAttachment('PROCESSO_TROCA_DE_POSTOS' . date('d-m-Y') . '.xlsx', 'PROCESSO_TROCA_DE_POSTOS' . date('d-m-Y') . '.xlsx');

            $mail->AddAddress(env('EMAIL_LOG'));
            if (!empty($retorno['Cadastro de Postos']) || !empty($retorno['Trocas de Postos'])) {
                $mail->AddAddress('parcerias@flexsystems.com.br');                      
            }

            if (!$mail->Send()) {
                $this->error("Erro: {$mail->ErrorInfo}");
                var_dump($mail->ErrorInfo);
            }else{
                $this->insertDisparoLog(date('Y-m-d H:i:s'));
            }
            unlink('PROCESSO_TROCA_DE_POSTOS' . date('d-m-Y') . '.xlsx');
        }
    }

    private function insertDisparoLog($dataInicio) {
        $sql =
        "
            UPDATE ".env('DB_OWNER')."FLEX_DATA_LOG_EMAIL
            SET DATA_INICIO = CONVERT(DATETIME, '{$dataInicio}', 120)
            WHERE ID = 1
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
            WHERE ID  = 1
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
}