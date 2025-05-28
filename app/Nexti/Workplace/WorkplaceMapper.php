<?php

namespace App\Nexti\Workplace;

use App\Nexti\Workplace\Dtos\CreateWorkplaceDto;

class WorkplaceMapper
{
    public static function create(object $data): CreateWorkplaceDto
    {
        return new CreateWorkplaceDto([
            'externalId' => $data->EXTERNALID,
            'name' => $data->POSTRA . ' - ' . $data->NAME,
            'active' => $data->ACTIVE == 1 ? true : false,
            'zipCode' => $data->ZIPCODE,
            'address' => $data->ADDRESS,
            'addressNumber' => $data->ADDRESSNUMBER,
            'district' => $data->DISTRICT,
            'businessUnitId' => 9130,
            'checkinTolerance' => $data->CHECKINTOLERANCE,
            'checkoutTolerance' => $data->CHECKOUTTOLERANCE,
            'externalClientId' => $data->EXTERNALCLIENTID,
            'companyId' => $data->COMPANYID,
            'companyNumber' => $data->COMPANYNUMBER,
            'companyName' => $data->COMPANYNAME,
            'contractNumber' => $data->CONTRACTNUMBER,
            'costCenter' => $data->COSTCENTER,
            'deparment' => $data->DEPARTMENT,
            'startDate' => date('dmYHis', strtotime($data->STARTDATE)),
            'forceCompanyTransfer' => $data->FORCECOMPANYTRANSFER == 1 ? true : false,
            'generateVirtualClocking' => $data->GENERATEVIRTUALCLOCKING == 1 ? true : false,
            'managerPhone' => $data->MANAGERPHONE,
            'managerName' => $data->MANAGERNAME,
            'phone' => $data->PHONE,
            'phone2' => $data->PHONE2,
            'serviceTypeId' => 5646,
            'service' => $data->SERVICE,
            'timezone' => $data->TIMEZONE,
            'vacantJob' => intval($data->VACANTJOB),
            'workPlaceNumber' => $data->WORKPLACENUMBER,
        ]);
    }
}