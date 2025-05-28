<?php

namespace App\Nexti\Person\Dtos;

use App\Shared\Nexti\BaseDto;

class CreatePersonDto extends BaseDto
{
    /** 
     * Endereço
     * @var null|string $address 
     **/
    public string $address;

    /** 
     * Adminstrador do dispositivo
     * @var bool $adminDevice 
     **/
    public bool $adminDevice = false;

    /** 
     * Data de admissão format ddMMyyyyHHmmss
     * @var string $admissionDate 
     **/
    public string $admissionDate;

    /** 
     * Permitir senha no dispositivo
     * @var bool $allowDevicePassword 
     **/
    public bool $allowDevicePassword;

    /** 
     * Permitir acesso mobile
     * @var bool $allowMobileClocking 
     **/
    public bool $allowMobileClocking;

    /**
     * Data de aniversário
     * @var string $birthDate
     * @example ddMMyyyyHHmmss
     **/
    public string $birthDate;

    /**
     * ID da unidade de negócio
     * @var integer $businessUnitId
     **/
    public int $businessUnitId;

    /**
     * Nome da unidade de negócio
     * @var string $businessUnitName
     **/
    public string $businessUnitName;

    /**
     * ID do cargo
     * @var integer $careerId
     **/
    public int $careerId;

    /**
     * Cidade
     * @var string $city
     **/
    public string $city;

    /**
     * ID da empresa
     * @var integer $companyId
     **/
    public int $companyId;

    /**
     * Complemento
     * @var string $complement
     **/
    public string $complement;

    /**
     * País
     * @var string $country
     **/
    public string $country;

    /**
     * CPF do colaborador
     * @var string $cpf
     **/
    public string $cpf;

    /**
     * Data de demissão
     * @var string $demissionDate
     * @example ddMMyyyyHHmmss
     **/
    public string $demissionDate;

    /**
     * Bairro
     * @var string $district
     **/
    public string $district;

    /**
     * E-mail do colaborador
     * @var string $email
     **/
    public string $email;

    /**
     * Matrícula do colaborador
     * @var string $enrolment
     **/
    public string $enrolment;

    /**
     * ID externo da unidade de negócios
     * @var string $externalBusinessUnitId
     **/
    public string $externalBusinessUnitId;

    /**
     * ID externo do cargo
     * @var string $externalCareerId
     **/
    public string $externalCareerId;

    /**
     * ID externo da empresa
     * @var string $externalCompanyId
     **/
    public string $externalCompanyId;

    /**
     * ID externo do colaborador
     * @var string $externalId
     **/
    public string $externalId;

    /**
     * ID externo da escala
     * @var string $externalScheduleId
     **/
    public string $externalScheduleId;

    /**
     * ID externo do horário
     * @var string $externalShiftId
     **/
    public string $externalShiftId;

    /**
     * ID externo do sindicato
     * @var string $externalTradeUnionId
     **/
    public string $externalTradeUnionId;

    /**
     * ID externo do posto
     * @var string $externalWorkplaceId
     **/
    public string $externalWorkplaceId;

    /**
     * Nome da mãe
     * @var string $fathersName
     **/
    public string $fathersName;

    /**
     * UF do Estado
     * @var string $federatedUnitInitials
     * @example SC, SP
     **/
    public string $federatedUnitInitials;

    /**
     * Gênero
     * @var string $gender
     **/
    public string $gender;

    /**
     * Número da casa
     * @var string $houseNumber
     **/
    public string $houseNumber;

    /**
     * ID do colaborador
     * @var integer $id
     **/
    public int $id;

    /**
     * Ignorar Apuração
     * @var bool $ignoreTimeTracking
     * @example false
     **/
    public bool $ignoreTimeTracking;

    /**
     * Ignorar validação
     * @var bool $ignoreValidation
     * @example false
     **/
    public bool $ignoreValidation;

    /**
     * Estado emissor. Tamanho máximo 2 (dois) caracteres
     * @var string $initialsRegisterNumber
     * @example RJ
     **/
    public string $initialsRegisterNumber;

    /**
     * Data de emissão
     * @var string $issueDateRegisterNumber
     * @example ddMMyyyyHHmmss
     **/
    public string $issueDateRegisterNumber;

    /**
     * ID externo do estado civil
     * @var string $maritalStatusExternalId
     * @example 002
     **/
    public string $maritalStatusExternalId;

    /**
     * ID do estado civil
     * @var integer $maritalStatusId
     **/
    public int $maritalStatusId;

    /**
     * Nome do estado civil
     * @var string $maritalStatusName
     * @example Solteiro
     **/
    public string $maritalStatusName;

    /**
     * Horários
     * @var array $markings
     * @example [480, 720, 760, 880]
     **/
    public array $markings;

    /**
     * Nome da mãe
     * @var string $mothersName
     **/
    public string $mothersName;

    /**
     * Nome do colaborador
     * @var string $name
     **/
    public string $name;

    /**
     * Nome do cargo
     * @var string $nameCareer
     **/
    public string $nameCareer;

    /**
     * Nome da escala
     * @var string $nameSchedule
     **/
    public string $nameSchedule;

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
     * ID do tipo de colaborar.
     * Valores:
     * [ID - 1] - COLABORADOR
     * [ID - 2] - TERCERIZADO
     * @var integer $personTypeId
     **/
    public int $personTypeId;

    /**
     * Telefone do colaborador
     * @var string $phone
     **/
    public string $phone;

    /**
     * Telefone 2 do colaborador
     * @var string $phone2
     **/
    public string $phone2;

    /**
     * PIS do colaborador
     * @var string $pis
     **/
    public string $pis;


    /** 
     * Tipo de Logradouro: Rua, avenida, Travessa
     * @var null|string $publicArea
     **/
    public ?string $publicArea;

    /** 
     * Data de referência: 22092021101010
     * @var null|string $referenceDate
     **/
    public ?string $referenceDate;

    /** 
     * Registro Geral: 999.999
     * @var null|string $registerNumber
     **/
    public ?string $registerNumber;

    /** 
     * Código da turma
     * @var int $rotationCode
    * */
    public int $rotationCode;

    /** 
     * ID da turma
     * @var int $rotationId
    * */
    public int $rotationId;

    /** 
     * Carga horária diária em minutos
     * @var string $scheduleDailyWorkload
     **/
    public int $scheduleDailyWorkload;

    /** 
     * ID da escala
     * @var int $scheduleId
     * */
    public int $scheduleId;

    /** 
     * Carga horária mensal em minutos
     * @var string $scheduleMonthlyWorkload
     **/
    public int $scheduleMonthlyWorkload;

    /** 
     * ID do horário
     * @var int $shiftId
     * */
    public int $shiftId;

    /** 
     * Estado
     * @var null|string $state
     * */
    public ?string $state;

    /** 
     * ID do sindicato
     * @var string $tradeUnionId
     **/
    public int $tradeUnionId;

    /** 
     * ID do Usuário
     * @var int $userAccountId
     * */
    public int $userAccountId;

    /** 
     * ID do posto
     * @var int $workplaceId
     * */
    public int $workplaceId;

    /** 
     * Nome do posto
     * @var null|string $workplaceName
     * */
    public ?string $workplaceName;

    /** 
     * Código postal
     * @var null|string $zipCode
     * */
    public ?string $zipCode;
}