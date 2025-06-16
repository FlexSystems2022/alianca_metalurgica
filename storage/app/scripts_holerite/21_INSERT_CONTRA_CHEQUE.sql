INSERT INTO nexti.dbo.FLEX_CONTRA_CHEQUE
SELECT DISTINCT
    ISNULL((
        SELECT RD_VALOR
        FROM nexti.dbo.SRD010 SRD1
        WHERE SRD1.RD_FILIAL = SRD010.RD_FILIAL COLLATE database_default
          AND SRD1.RD_MAT = SRD010.RD_MAT COLLATE database_default
          AND SRD1.RD_PERIODO = SRD010.RD_PERIODO COLLATE database_default
          AND SRD1.RD_PD = '731'
    ), 0) AS baseFgts,

    ISNULL((
        SELECT SUM(RD_VALOR)
        FROM nexti.dbo.SRD010 SRD1
        WHERE SRD1.RD_FILIAL = SRD010.RD_FILIAL COLLATE database_default
          AND SRD1.RD_MAT = SRD010.RD_MAT COLLATE database_default
          AND SRD1.RD_PERIODO = SRD010.RD_PERIODO COLLATE database_default
          AND SRD1.RD_PD IN ('731')
    ), 0) AS baseInss,

    ISNULL((
        SELECT RD_VALOR
        FROM nexti.dbo.SRD010 SRD1
        WHERE SRD1.RD_FILIAL = SRD010.RD_FILIAL COLLATE database_default
          AND SRD1.RD_MAT = SRD010.RD_MAT COLLATE database_default
          AND SRD1.RD_PERIODO = SRD010.RD_PERIODO COLLATE database_default
          AND SRD1.RD_PD = '731'
    ), 0) AS baseIrrf,

    nexti.dbo.FLEX_EMPRESA.IDEXTERNO AS companyExternalId,
    nexti.dbo.FLEX_EMPRESA.ID AS companyId,
    NULL AS companyNumber,
    RD_VALOR AS grossPay,

    ISNULL((
        SELECT RD_VALOR
        FROM nexti.dbo.SRD010 SRD1
        WHERE SRD1.RD_FILIAL = SRD010.RD_FILIAL COLLATE database_default
          AND SRD1.RD_MAT = SRD010.RD_MAT COLLATE database_default
          AND SRD1.RD_PERIODO = SRD010.RD_PERIODO COLLATE database_default
          AND SRD1.RD_PD = '732'
    ), 0) AS monthFgts,

    (CAST(SUBSTRING(RD_PERIODO, 0, 5) AS VARCHAR(4)) + '-' + 
     CAST(SUBSTRING(RD_PERIODO, 5, 3) AS VARCHAR(4)) + '-01') AS paycheckPeriodDate,

    nexti.dbo.FLEX_CONTRA_CHEQUE_COMPETENCIA.ID AS paycheckPeriodId,
    nexti.dbo.FLEX_CONTRA_CHEQUE_COMPETENCIA.IDEXTERNO AS paycheckPeriodName,
    nexti.dbo.FLEX_COLABORADOR.NUMCAD AS personEnrolment,
    nexti.dbo.FLEX_COLABORADOR.IDEXTERNO AS personExternalId,
    nexti.dbo.FLEX_COLABORADOR.ID AS personId,
    0 AS ID,

    (CAST(nexti.dbo.FLEX_EMPRESA.IDEXTERNO COLLATE database_default AS VARCHAR(50)) + '-' +
     nexti.dbo.FLEX_COLABORADOR.IDEXTERNO + '-' +
     CAST(SRD010.RD_PERIODO AS VARCHAR(50))) AS IDEXTERNO,

    0 AS TIPO,
    0 AS SITUACAO,
    '' AS OBSERVACAO,
    nexti.dbo.FLEX_EMPRESA.NUMEMP,
    nexti.dbo.FLEX_EMPRESA.CODFIL,
    nexti.dbo.FLEX_CONTRA_CHEQUE_COMPETENCIA.IDEXTERNO AS CONTRA_CHEQUE_CMP
FROM ALIANCA.dbo.SRD010
JOIN ALIANCA.dbo.SRA010
    ON SRA010.RA_MAT = SRD010.RD_MAT
    AND SRA010.RA_FILIAL = SRD010.RD_FILIAL
    AND SRA010.D_E_L_E_T_ = ''
JOIN nexti.dbo.FLEX_EMPRESA
    ON nexti.dbo.FLEX_EMPRESA.CODFIL = LEFT(SRD010.RD_FILIAL, 2) COLLATE database_default
JOIN nexti.dbo.FLEX_COLABORADOR
    ON nexti.dbo.FLEX_COLABORADOR.CPF = LTRIM(RTRIM(SRA010.RA_CIC)) COLLATE database_default
    AND nexti.dbo.FLEX_COLABORADOR.CODFIL = SRD010.RD_FILIAL collate database_default
    AND (nexti.dbo.FLEX_COLABORADOR.DATADEMISSAO = '' OR nexti.dbo.FLEX_COLABORADOR.DATADEMISSAO IS NULL)
JOIN nexti.dbo.FLEX_CONTRA_CHEQUE_COMPETENCIA
    ON nexti.dbo.FLEX_CONTRA_CHEQUE_COMPETENCIA.IDEXTERNO =
        (CAST(nexti.dbo.FLEX_EMPRESA.IDEXTERNO COLLATE database_default AS VARCHAR(50)) + '-' +
         CAST(SRD010.RD_PERIODO AS VARCHAR(50))) COLLATE database_default
    AND nexti.dbo.FLEX_CONTRA_CHEQUE_COMPETENCIA.ID > 0

WHERE RD_ROTEIR = 'FOL'
  AND RD_PROCES = '00001'
  AND (SRD010.R_E_C_D_E_L_ = 0 OR SRD010.R_E_C_D_E_L_ = '')
  AND (
        RD_PD = '101'
     OR (
        RD_PD = '700'
        AND NOT EXISTS (
            SELECT 1
            FROM ALIANCA.dbo.SRD010 SRD_SUB
            WHERE SRD_SUB.RD_PD = '101'
              AND SRD_SUB.RD_MAT = SRD010.RD_MAT
              AND SRD_SUB.RD_FILIAL = SRD010.RD_FILIAL
              AND SRD_SUB.RD_PERIODO = SRD010.RD_PERIODO
              AND SRD_SUB.RD_ROTEIR = 'FOL'
              AND SRD_SUB.RD_PROCES = '00001'
              AND (SRD_SUB.R_E_C_D_E_L_ = 0 OR SRD_SUB.R_E_C_D_E_L_ = '')
        )
     )
  )
  AND SRD010.RD_PERIODO >= '202501'
  AND NOT EXISTS (
      SELECT 1
      FROM nexti.dbo.FLEX_CONTRA_CHEQUE
      WHERE nexti.dbo.FLEX_CONTRA_CHEQUE.IDEXTERNO =
            (CAST(nexti.dbo.FLEX_EMPRESA.IDEXTERNO COLLATE database_default AS VARCHAR(50)) + '-' +
             nexti.dbo.FLEX_COLABORADOR.IDEXTERNO + '-' +
             CAST(SRD010.RD_PERIODO AS VARCHAR(50)))
  );
