<?php

namespace App\Nexti\Person;

use App\Nexti\Person\Dtos\CreatePersonDto;

class PersonMapper
{
    public static function create(object $data): CreatePersonDto
    {
        return new CreatePersonDto([
            'admissionDate'         => implode('', array_reverse(explode('-', explode(' ', $data->DATADM)[0]))) . '000000',
            'allowDevicePassword'   => false,
            'allowMobileClocking'   => false,
            'externalId'            => $data->IDEXTERNO,
            'name'                  => $data->NOMFUN,
            'personSituationId'     => $data->SITFUN == 'D' ? 3 : 1,
            'personTypeId'          => intval($data->TIPCOL) == 1 ? 1 : 2,
            'cpf'                   => str_pad($data->CPF, 11, '0', STR_PAD_LEFT),
            'enrolment'             => $data->NUMCAD,
            'pis'                   => $data->PIS,
            'birthDate'             => implode('', array_reverse(explode('-', explode(' ', $data->DATANASCIMENTO)[0]))) . '000000',
            'phone'                 => $data->TELEFONE,
            //'street'                => $data->BAIRRO,
            'city'                  => $data->CIDADE,
            'address'               => $data->ENDERECO,
            'companyId'             => intval($data->IDEMPRESA),
            'careerId'              => intval($data->IDCARGO),
            'zipCode'               => $data->CEP,
            //'houseNumber'           => $data->endnum,
            'district'              => $data->BAIRRO,
            'state'                 => $data->UF,
            'country'               => $data->PAIS,
            'gender'                => $data->GENERO == 1 ? 'M' : 'F',
            'ignoreValidation'      => true,
            'registerNumber'        => $data->RG,
            'fathersName'           => $data->PAI,
            'mothersName'           => $data->MAE,
            'scheduleId'            => 200315,
            'rotationCode'          => 1
        ]);
    }
}