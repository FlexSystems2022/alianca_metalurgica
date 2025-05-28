<?php

namespace App\Helper;

use Illuminate\Support\Facades\DB;
use Datetime;

class LogColaborador
{
	private $numemp = null;

	private $numcad = null;

	private $processo = null;

	private $uri = null;

	private $payload = null;

	private $response = null;

	public function empresa(int $numemp): self
	{
		$this->numemp = $numemp;

		return $this;
	}

	public function matricula(string $numcad): self
	{
		$this->numcad = $numcad;

		return $this;
	}

	public function processo(string $processo): self
	{
		$this->processo = $processo;

		return $this;
	}

	public function uri(string $uri): self
	{
		$this->uri = $uri;

		return $this;
	}
	
	public function payload($payload): self
	{
		$this->payload = json_encode($payload);

		return $this;
	}

	public function response($response): self
	{
		$this->response = json_encode($response);

		return $this;
	}

	public function save(): void
	{
	    $data = [
	        'NUMEMP' => $this->numemp,
	        'NUMCAD' => $this->numcad,
	        'PROCESSO' => $this->processo,
	        'URI' => $this->uri,
	        'PAYLOAD' => $this->payload,
	        'RESPONSE' => $this->response,
	        'DATA' => DB::raw("CONVERT(DATETIME,'" . (new DateTime())->format('Y-m-d H:i:s') . "', 120)")
	    ];

	    DB::connection('sqlsrv')
	        ->table(env('DB_OWNER')."LOG_COLABORADOR")
	        ->insert($data);
	}

    public static function init()
    {
        return new static;
    }
}