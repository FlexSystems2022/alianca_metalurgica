<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessaIntermediarioTrocaPostos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaIntermediarioTrocaPostos';

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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // LOCAL DOS SCRIPTS
        $localScript = base_path().Storage::url('app/scripts_troca_postos')."/";
        //$localScript = base_path() . '\\resources\\scripts_sql\\';
        $dataInicio = date('Y-m-d H:i:s A');
        $files = scandir($localScript);
        
        $contador = 1;
        foreach ($files as $key => $value) {
            if ($key != '.' && $key != '..') {
                if ($key > 1) {
                    $sql = file_get_contents($localScript . $value);
                    echo $value . "################################################### \n\r";
                    try {
                        DB::connection('sqlsrv')->statement($sql);
                        echo $contador;
                        $contador ++;
                    } catch (\Exception $e) {
                        echo $value." erro: ".$e->getMessage()."\n\r";
                    }
                }
            }
        }
        $dataFim = date('Y-m-d H:i:s A');
    }
}
