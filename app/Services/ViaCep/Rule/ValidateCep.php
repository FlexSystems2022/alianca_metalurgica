<?php

namespace App\Services\ViaCep\Rule;

use App\Services\ViaCep\Exceptions\CepException;

class ValidateCep
{
	/**
	 * Validate
	 */
	public static function validate(?string $cep=null): string
	{
		$class = new static;

		$value = $class->preprate($cep);
		if(!$value) {
            throw CepException::invalidCep($cep);
		}

		$validMatch = preg_match("/^[0-9]{8}$/", $value);
		if(!$validMatch) {
            throw CepException::invalidCep($cep);
		}

		return $value;
	}

	private function preprate(?string $cep=null):string
    {
        return trim(str_replace('-', '', $cep ?? ''));
    }
}