<?php
namespace App\Nexti\Workplace\Dtos;

use App\Shared\Nexti\BaseDto;

class CreateWorkplaceDto extends BaseDto
{
        /**
     * Ativo
     * @var bool $active
     **/
    public bool $active;

    /**
     * Código postal
     * @var string $zipCode
     **/
    public string $zipCode;

    /**
     * Endereço
     * @var string $address
     **/
    public string $address;

    /**
     * Número do endereço
     * @var string $addressNumber
     **/
    public string $addressNumber;

    /**
     * Bairro
     * @var string $district
     **/
    public string $district;

    /**
     * Unidade de negócio
     * @var int $businessUnitId
     **/
    public int $businessUnitId;

    /**
     * Tolerância de entrada em minutos
     * @var int $checkinTolerance
     **/
    public int $checkinTolerance;

    /**
     * Tolerância de saída em minutos
     * @var int $checkoutTolerance
     **/
    public int $checkoutTolerance;

    /**
     * ID externo do cliente
     * @var string $externalClientId
     **/
    public string $externalClientId;

    /**
     * ID da empresa
     * @var int $companyId
     **/
    public int $companyId;

    /**
     * Número da empresa
     * @var string $companyNumber
     **/
    public string $companyNumber;

    /**
     * Nome da empresa
     * @var string $companyName
     **/
    public string $companyName;

    /**
     * Número do contrato (formato numérico)
     * @var int $contractNumber
     **/
    public int $contractNumber;

    /**
     * Centro de custo
     * @var string $costCenter
     **/
    public string $costCenter;

    /**
     * Departamento
     * @var string $deparment
     **/
    public string $deparment;

    /**
     * Data de inicio do posto (formato ddMMyyyyHHmmss)
     * @var string $startDate
     **/
    public string $startDate;

    /**
     * Forçar transferência na mesma empresa
     * @var bool $forceCompanyTransfer
     **/
    public bool $forceCompanyTransfer;

    /**
     * Gerar marcação virtual no lugar de esquecimento
     * @var bool $generateVirtualClocking
     **/
    public bool $generateVirtualClocking;

    /**
     * Telefone do gestor do posto
     * @var string $managerPhone
     **/
    public string $managerPhone;

    /**
     * Nome do gestor do posto
     * @var string $managerName
     **/
    public string $managerName;

    /**
     * Telefone
     * @var string $phone
     **/
    public string $phone;

    /**
     * Telefone2
     * @var string $phone2
     **/
    public string $phone2;

    /**
     * ID do tipo de serviço
     * @var int $serviceTypeId
     **/
    public int $serviceTypeId;

    /**
     * Serviço
     * @var string $service
     **/
    public string $service;

    /**
     * Timezone (exemplo: America/Sao_Paulo)
     * @var string $timezone
     **/
    public string $timezone;

    /**
     * Vagas
     * @var int $vacantJob
     **/
    public int $vacantJob;

    /**
     * Número do posto (formato alfanumérico)
     * @var string $workPlaceNumber
     **/
    public string $workPlaceNumber;
}