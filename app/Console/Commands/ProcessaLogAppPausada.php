<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Mail\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\bibliotecas\PHPMailer;
use App\Http\Controllers\Controller;
use View;

class ProcessaLogAppPausada extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaLogAppPausada';

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
    }


    private function enviaEmailLog()
    {
        $sucesso = true;
        $mensagem = '';
        $mailPos = new PHPMailer();
        $mailPos->SMTPSecure = 'tls';
        $mailPos->SMTP_PORT = 587;
        $mailPos->IsSMTP();
        $mailPos->Host = 'mail.flexsystems.com.br';
        $mailPos->SMTPAuth = true;
        $mailPos->Username = "nao-responder@flexsystems.com.br";
        $mailPos->Password = "!%iH%rOYxCUY";
        $mailPos->From = "nao-responder@flexsystems.com.br";
        $mailPos->FromName = 'Flex Systems';
        $mailPos->charSet = "UTF-8";

        $possuiRegistro = false;

        $mailPos->WordWrap = 50;
        $mailPos->IsHTML(true);

        $mailPos->Subject = "ATENCAO! INTEGRACAO PARADA!- SPSP - NEXTI";

        $dataUltimoDisparo = $this->verificaDisparoLog();

        $dataHoraAtual = date("Y-m-d H:i:s");
        $start = new \DateTime($dataUltimoDisparo[0]->DATA_INICIO);
        $end = new \DateTime($dataHoraAtual);

        $interval = $start->diff($end);

        if($interval->h >= 1){
            $mailPos->Body = "Integracao nao executa a mais de 1 hora!";

            $mailPos->AddAddress('parcerias.suporte@flexsystems.com.br');

            if (!$mailPos->Send()) {
                $sucesso = false;
                $mensagem = 'Erro: ' . $mailPos->ErrorInfo;
            }
        }else{
            $sucesso = false;
        }

        var_dump($sucesso);
    }

    private function verificaDisparoLog(){
        $sql = 
        "
            SELECT MAX(DATA_INICIO) AS DATA_INICIO FROM ".env('DB_OWNER').".dbo.NEXTI_LOG
        ";

        return DB::connection('sqlsrv')->select($sql);
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
        $retColaboradoresNovos = $this->enviaEmailLog();
    }
}
