<?php

namespace App\Nexti\Person\Dtos;

class CreatePersonDto
{
    public ?string $dtoAddress = null;

    /**
 * Nome do posto
 * @var string $workplaceName
 **/
public string $workplaceName;

/**
 * ID do estado civil
 * @var integer $maritalStatusId
 **/
public int $maritalStatusId;

/**
 * Gênero
 * @var string $gender
 **/
public string $gender;

/**
 * Nome da mãe
 * @var string $fathersName
 **/
public string $fathersName;

/**
 * Nome da escala
 * @var string $nameSchedule
 **/
public string $nameSchedule;

/**
 * Telefone 2 do colaborador
 * @var string $phone2
 **/
public string $phone2;

/**
 * ID da situação do colaborar.
 * Valores:
 * [ID - 1] - TRABALHANDO
 * [ID - 2] - AUSENTE
 * [ID - 3] - DEMITIDO
 * @var integer $personSituationId
 **/
public int $personSituationId;

/**
 * ID externo do cargo
 * @var string $externalCareerId
 **/
public string $externalCareerId;

/**
 * Matrícula do colaborador
 * @var string $enrolment
 **/
public string $enrolment;

/**
 * Adminstrador do dispositivo
 * @var bool $adminDevice
 * @example false
 **/
public bool $adminDevice;

/**
 * Complemento
 * @var string $complement
 **/
public string $complement;

/**
 * ID externo do colaborador
 * @var string $externalId
 **/
public string $externalId;

/**
 * ID do sindicato
 * @var integer $tradeUnionId
 **/
public int $tradeUnionId;

/**
 * ID externo do posto
 * @var string $externalWorkplaceId
 **/
public string $externalWorkplaceId;

/**
 * Nome do cargo
 * @var string $nameCareer
 **/
public string $nameCareer;

/**
 * Número da casa
 * @var string $houseNumber
 **/
public string $houseNumber;

/**
 * ID do tipo de colaborar.
 * Valores:
 * [ID - 1] - COLABORADOR
 * [ID - 2] - TERCERIZADO
 * @var integer $personTypeId
 **/
public int $personTypeId;

/**
 * ID externo da unidade de negócios
 * @var string $externalBusinessUnitId
 **/
public string $externalBusinessUnitId;

/**
 * Cidade
 * @var string $city
 **/
public string $city;

/**
 * Código da turma
 * @var integer $rotationCode
 **/
public int $rotationCode;

/**
 * Nome do colaborador
 * @var string $name
 **/
public string $name;

/**
 * Ignorar validação
 * @var bool $ignoreValidation
 * @example false
 **/
public bool $ignoreValidation;

/**
 * ID externo do estado civil
 * @var string $maritalStatusExternalId
 * @example 002
 **/
public string $maritalStatusExternalId;

/**
 * Registro Geral
 * @var string $registerNumber
 * @example 999.999
 **/
public string $registerNumber;

/**
 * Data de aniversário
 * @var string $birthDate
 * @example ddMMyyyyHHmmss
 **/
public string $birthDate;

/**
 * ID externo da empresa
 * @var string $externalCompanyId
 **/
public string $externalCompanyId;

/**
 * Carga horária mensal em minutos
 * @var integer $scheduleMonthlyWorkload
 **/
public int $scheduleMonthlyWorkload;

/**
 * Código postal
 * @var string $zipCode
 **/
public string $zipCode;

/**
 * Telefone do colaborador
 * @var string $phone
 **/
public string $phone;

/**
 * Bairro
 * @var string $district
 **/
public string $district;

/**
 * Nome da unidade de negócio
 * @var string $businessUnitName
 **/
public string $businessUnitName;

/**
 * ID do posto
 * @var integer $workplaceId
 **/
public int $workplaceId;

/**
 * Estado
 * @var string $state
 **/
public string $state;

/**
 * ID externo do sindicato
 * @var string $externalTradeUnionId
 **/
public string $externalTradeUnionId;

/**
 * Nome do estado civil
 * @var string $maritalStatusName
 * @example Solteiro
 **/
public string $maritalStatusName;

/**
 * ID da escala
 * @var integer $scheduleId
 **/
public int $scheduleId;

/**
 * ID externo do horário
 * @var string $externalShiftId
 **/
public string $externalShiftId;

/**
 * Data de emissão
 * @var string $issueDateRegisterNumber
 * @example ddMMyyyyHHmmss
 **/
public string $issueDateRegisterNumber;

/**
 * Endereço
 * @var string $address
 **/
public string $address;

/**
 * Tipo de Logradouro
 * @var string $publicArea
 * @example Rua, avenida, Travessa
 **/
public string $publicArea;

/**
 * País
 * @var string $country
 **/
public string $country;

/**
 * Ignorar Apuração
 * @var bool $ignoreTimeTracking
 * @example false
 **/
public bool $ignoreTimeTracking;

/**
 * Data de admissão
 * @var string $admissionDate
 * @example ddMMyyyyHHmmss
 **/
public string $admissionDate;

/**
 * UF do Estado
 * @var string $federatedUnitInitials
 * @example SC, SP
 **/
public string $federatedUnitInitials;

/**
 * ID da empresa
 * @var integer $companyId
 **/
public int $companyId;

/**
 * Data de demissão
 * @var string $demissionDate
 * @example ddMMyyyyHHmmss
 **/
public string $demissionDate;

/**
 * ID do cargo
 * @var integer $careerId
 **/
public int $careerId;

/**
 * ID da turma
 * @var integer $rotationId
 **/
public int $rotationId;

/**
 * CPF do colaborador
 * @var string $cpf
 **/
public string $cpf;

/**
 * Data de referência
 * @var string $referenceDate
 * @example 22092021101010
 **/
public string $referenceDate;

/**
 * Carga horária diária em minutos
 * @var integer $scheduleDailyWorkload
 **/
public int $scheduleDailyWorkload;

/**
 * PIS do colaborador
 * @var string $pis
 **/
public string $pis;

/**
 * Permitir senha no dispositivo
 * @var bool $allowDevicePassword
 * @example false
 **/
public bool $allowDevicePassword;

/**
 * Nome da mãe
 * @var string $mothersName
 **/
public string $mothersName;

/**
 * ID do colaborador
 * @var integer $id
 **/
public int $id;

/**
 * Estado emissor. Tamanho máximo 2 (dois) caracteres
 * @var string $initialsRegisterNumber
 * @example RJ
 **/
public string $initialsRegisterNumber;

/**
 * E-mail do colaborador
 * @var string $email
 **/
public string $email;

/**
 * ID externo da escala
 * @var string $externalScheduleId
 **/
public string $externalScheduleId;

/**
 * ID do Usuário
 * @var integer $userAccountId
 **/
public int $userAccountId;

/**
 * ID do horário
 * @var integer $shiftId
 **/
public int $shiftId;

/**
 * Horários
 * @var array $markings
 * @example [480, 720, 760, 880]
 **/
public array $markings;

/**
 * ID da unidade de negócio
 * @var integer $businessUnitId
 **/
public int $businessUnitId;

/**
 * Permitir acesso mobile
 * @var bool $allowMobileClocking
 * @example false
 **/
 public bool $allowMobileClocking;
}