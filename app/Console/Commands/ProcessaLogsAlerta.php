<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\bibliotecas\PHPMailer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProcessaLogsAlerta extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaLogsAlerta {--teste}';

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
        $alerta = [];

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

        $mail->Subject      = "RESOLV - Alerta - " . date("d/m/Y");

        // Alerta
        $sql = 
        "
            SELECT 
                ".env('DB_OWNER')."NEXTI_ALERTA.NUMEMP,
                ".env('DB_OWNER')."NEXTI_ALERTA.CODFIL,
                ".env('DB_OWNER')."NEXTI_ALERTA.NUMCAD,
                ".env('DB_OWNER')."NEXTI_ALERTA.NOMFUN
            FROM ".env('DB_OWNER')."NEXTI_ALERTA 
            WHERE ".env('DB_OWNER')."NEXTI_ALERTA.ENVIADO = 'N'
        ";

        $itens = DB::connection('sqlsrv')->select($sql);
        if (sizeof($itens) > 0) {
            $possuiRegistro = true;
            foreach ($itens as $item) {
                $alerta[] = [
                    'EMPRESA' => $item->NUMEMP,
                    'FILIAL' => $item->CODFIL,
                    'MATRICULA' => $item->NUMCAD,
                    'COLABORADOR' => $item->NOMFUN
                ];
            }
        }

        $retorno = [
            'alerta' => $alerta,
        ];

        $mensagens = [
            "alerta"                => [
                'Colaboradores a terem admissão cancelada.',
                '',
                ''
            ]
        ];

        if(1==1){
            $planilha = new Spreadsheet();
            $planilhaProvisoria = new Spreadsheet();
            $tableSheetIndex = 1;
            foreach ($retorno as $index => $itens) {
                if (!$itens) {
                    continue;
                }

                $aba = $planilhaProvisoria->createSheet($tableSheetIndex);
                $aba->setTitle($index);
                if (isset($mensagens[$index])) {
                    $aba->setCellValue('A1', $mensagens[$index][0]);
                    $aba->setCellValue('A2', $mensagens[$index][1]);
                    $aba->setCellValue('A3', $mensagens[$index][2]);
                } else {
                    // Código a ser executado se $index não existir no array
                    // Por exemplo, você pode definir valores padrão ou lidar com a situação de outra forma.
                    $aba->setCellValue('A1', ' ');
                    $aba->setCellValue('A2', ' ');
                    $aba->setCellValue('A3', ' ');
                }

                $linhaIndex = 5;

                $this->info("Criando planilha: {$index}");

                $colTipo = 99;
                foreach ($itens as $linha => $valores) {
                    $colunaIndex = 1;

                    foreach ($valores as $coluna => $valor) {
                        if($linhaIndex == 5){
                            $aba->setCellValueByColumnAndRow($colunaIndex, $linhaIndex, $coluna);
                            if($coluna == 'TIPO'){
                                $colTipo = $colunaIndex;
                            }
                        }
                        $aba->getColumnDimensionByColumn($colunaIndex)->setAutoSize(true);

                        $tipos = [
                            0   =>  'inserção',
                            1   =>  'atualização',
                            3   =>  'exclusão'
                        ];
                        $situacoes = [
                            0   =>  'pendente',
                            1   =>  'sucesso',
                            2   =>  'erro'
                        ];
                        if($colunaIndex == $colTipo){
                            $aba->setCellValueByColumnAndRow($colunaIndex, $linhaIndex + 1, $tipos[$valor]);
                        }
                        else if($colunaIndex == $colTipo + 1){
                            $aba->setCellValueByColumnAndRow($colunaIndex, $linhaIndex + 1, $situacoes[$valor]);
                        }
                        else{
                            $aba->setCellValueByColumnAndRow($colunaIndex, $linhaIndex + 1, $valor);
                        }
                        $colunaIndex ++;
                    }
                    $linhaIndex ++;
                }

                $aba->setAutoFilter('A5:' . chr(64 + $colunaIndex -1) . (count($valores) + 5));
                $planilha->addSheet($aba, $tableSheetIndex);

                $tableSheetIndex++;
            }

            $planilha->removeSheetByIndex(0);
            $writer = new Xlsx($planilha);

            $path = 'RESOLV_ADMISSOES_CANCELADAS_' . date('d-m-Y') . '.xlsx';
            $writer->save($path);

            $mail->addAttachment($path, $path);
            $mail->AddAddress(env('EMAIL_LOG'));
            if($this->option('teste')){
                $mail->Subject      = "RESOLV";
                if ($possuiRegistro) {
                    $mail->AddAddress('parcerias@flexsystems.com.br');
                    $mail->AddAddress('suporte@flexsystems.com.br');
                }
            } else {
                if ($possuiRegistro) {
                    $mail->AddAddress('parcerias@flexsystems.com.br');
                    $mail->AddAddress('suporte@flexsystems.com.br');
                }
            }

            if (!$mail->Send()) {
                $this->error("Erro: {$mail->ErrorInfo}");
            }else if(!$this->option('teste')) {
                foreach($itens as $item){
                    $query = 
                    "
                        UPDATE ".env('DB_OWNER')."NEXTI_ALERTA
                        SET ".env('DB_OWNER')."NEXTI_ALERTA.ENVIADO = 'S'
                        WHERE ".env('DB_OWNER')."NEXTI_ALERTA.NUMEMP = {$item['EMPRESA']}
                        AND ".env('DB_OWNER')."NEXTI_ALERTA.CODFIL = '{$item['FILIAL']}'
                        AND ".env('DB_OWNER')."NEXTI_ALERTA.NUMCAD = '{$item['MATRICULA']}'
                    ";
                    DB::connection('sqlsrv')->statement($query);                    
                }
            }

            unlink($path);
        }
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->enviaEmailLog();
    }
}