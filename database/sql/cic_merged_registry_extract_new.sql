/* ============================================================================
   CIC MERGED REGISTRY EXTRACT
   ----------------------------------------------------------------------------
   Combines the two production extracts:
       * "SQL query for CIC ID FINAL.sql"      -> subject / member master data
       * "SQLQuery8 query for CIC CI FINAL.sql" -> credit / contract data
   and adds a per-member loan roll-up so the whole thing can feed the
   member-registry Laravel app.

   Run in SSMS against the core-banking database. It returns TWO result sets:

       Result set 1  "Members"  -> export to members.xlsx  (import as "Members")
       Result set 2  "Loans"    -> export to loans.xlsx    (import as "Loans")

   Column headers of result set 1 are a superset of the old
   "SQL CIC output.xlsx"; result set 2 matches "SQL ID output.xlsx".
   The Laravel importer tolerates extra / missing columns.

   Provider Code is hard-coded to 'CO014030' (ORO Integrated Cooperative's
   assigned CIC provider code) in BOTH result sets below. Change both if it
   is ever reassigned.

   Branch Code in the Loans result set is the member's branch (T_CIF.BR ->
   T_BRPARMS.BrName), resolved exactly as in the Members result set.
   ============================================================================ */

SET NOCOUNT ON;

IF OBJECT_ID('tempdb..#IDIndividual') IS NOT NULL DROP TABLE #IDIndividual;
IF OBJECT_ID('tempdb..#LoanBase')     IS NOT NULL DROP TABLE #LoanBase;
IF OBJECT_ID('tempdb..#InstAgg')      IS NOT NULL DROP TABLE #InstAgg;
IF OBJECT_ID('tempdb..#NextInst')     IS NOT NULL DROP TABLE #NextInst;
IF OBJECT_ID('tempdb..#Guarantors')   IS NOT NULL DROP TABLE #Guarantors;
IF OBJECT_ID('tempdb..#LinkedSubs')   IS NOT NULL DROP TABLE #LinkedSubs;
IF OBJECT_ID('tempdb..#Purpose')      IS NOT NULL DROP TABLE #Purpose;
IF OBJECT_ID('tempdb..#CIReport')     IS NOT NULL DROP TABLE #CIReport;
IF OBJECT_ID('tempdb..#LoanAggByCID') IS NOT NULL DROP TABLE #LoanAggByCID;
GO

/* ============================================================================
   PART A  -  #IDIndividual   (verbatim from "SQL query for CIC ID FINAL.sql")
   Grain: one row per CIF.CID where CIF.Type = '001' (Personal / Individual)
   ============================================================================ */
SELECT
    'ID'                                                          AS [Record Type],
    'CO014030'                                                    AS [Provider Code],
    br.BrName                                                     AS [Branch Code],
    CONVERT(VARCHAR(8), CAST(GETDATE() AS DATE), 112)             AS [Subject Reference Date],
    c.CID                                                         AS [Provider Subject No],
    c.TitleCode                                                   AS [Title],
    c.Name2                                                       AS [First Name],
    c.Name1                                                       AS [Last Name],
    c.Name3                                                       AS [Middle Name],
    c.Name4                                                       AS [Suffix],
    CAST(NULL AS VARCHAR(50))                                     AS [Nickname],
    CAST(NULL AS VARCHAR(50))                                     AS [Previous Last Name],
    CASE c.GenderType WHEN '001' THEN 'M' WHEN '002' THEN 'F' END AS [Gender],
    c.BirthDate                                                   AS [Date of Birth],
    CAST(NULL AS VARCHAR(50))                                     AS [Place of Birth],
    CAST(NULL AS VARCHAR(10))                                     AS [Country of Birth],
    CAST(NULL AS VARCHAR(10))                                     AS [Nationality],
    CAST(NULL AS VARCHAR(5))                                      AS [Resident],
    c.CivilStatusCode                                             AS [Civil Status],
    CAST(NULL AS INT)                                             AS [Number of Dependents],
    CAST(NULL AS INT)                                             AS [Car/s Owned],
    sp.Name2                                                      AS [Spouse First Name],
    sp.Name1                                                      AS [Spouse Last Name],
    sp.Name3                                                      AS [Spouse Middle Name],
    CAST(NULL AS VARCHAR(50)) AS [Mother's Maiden First Name],
    CAST(NULL AS VARCHAR(50)) AS [Mother's Maiden Last Name],
    CAST(NULL AS VARCHAR(50)) AS [Mother's Maiden Middle Name],
    CAST(NULL AS VARCHAR(50)) AS [Father First Name],
    CAST(NULL AS VARCHAR(50)) AS [Father Last Name],
    CAST(NULL AS VARCHAR(50)) AS [Father Middle Name],
    CAST(NULL AS VARCHAR(10)) AS [Father Suffix],

    addr1.AddressType                                             AS [Address 1: Address Type],
    LTRIM(RTRIM(
        ISNULL(addr1.Line1,'') + ' ' + ISNULL(addr1.Line2,'') + ' ' +
        ISNULL(addr1.Line3,'') + ' ' + ISNULL(addr1.Line4,'')
    ))                                                            AS [Address 1: FullAddress],
    CAST(NULL AS VARCHAR(30))                                     AS [Address 1: StreetNo],
    addr1.PostalCode                                              AS [Address 1: PostalCode],
    CAST(NULL AS VARCHAR(30))                                     AS [Address 1: Subdivision],
    CAST(NULL AS VARCHAR(30))                                     AS [Address 1: Barangay],
    CAST(NULL AS VARCHAR(30))                                     AS [Address 1: City],
    CAST(NULL AS VARCHAR(30))                                     AS [Address 1: Province],
    CAST(NULL AS VARCHAR(5))                                      AS [Address 1: Country],
    CAST(NULL AS VARCHAR(5))                                      AS [Address 1: House Owner/Lessee],
    CAST(NULL AS DATE)                                            AS [Address 1: Occupied Since],

    addr2.AddressType                                             AS [Address 2: Address Type],
    LTRIM(RTRIM(
        ISNULL(addr2.Line1,'') + ' ' + ISNULL(addr2.Line2,'') + ' ' +
        ISNULL(addr2.Line3,'') + ' ' + ISNULL(addr2.Line4,'')
    ))                                                            AS [Address 2: FullAddress],
    CAST(NULL AS VARCHAR(30))                                     AS [Address 2: StreetNo],
    addr2.PostalCode                                              AS [Address 2: PostalCode],
    CAST(NULL AS VARCHAR(30))                                     AS [Address 2: Subdivision],
    CAST(NULL AS VARCHAR(30))                                     AS [Address 2: Barangay],
    CAST(NULL AS VARCHAR(30))                                     AS [Address 2: City],
    CAST(NULL AS VARCHAR(30))                                     AS [Address 2: Province],
    CAST(NULL AS VARCHAR(5))                                      AS [Address 2: Country],
    CAST(NULL AS VARCHAR(5))                                      AS [Address 2: House Owner/Lessee],
    CAST(NULL AS DATE)                                            AS [Address 2: Occupied Since],

    'NID'                                                         AS [Identification 1: Type],
    c.NID                                                         AS [Identification 1: Number],
    CAST(NULL AS VARCHAR(10))                                     AS [Identification 2: Type],
    CAST(NULL AS VARCHAR(20))                                     AS [Identification 2: Number],
    CAST(NULL AS VARCHAR(10))                                     AS [Identification 3: Type],
    CAST(NULL AS VARCHAR(20))                                     AS [Identification 3: Number],
    'NID'                                                         AS [ID 1: Type],
    c.NID                                                         AS [ID 1: Number],
    CAST(NULL AS DATE)         AS [ID 1: IssueDate],
    CAST(NULL AS VARCHAR(5))   AS [ID 1: IssueCountry],
    CAST(NULL AS DATE)         AS [ID 1: ExpiryDate],
    CAST(NULL AS VARCHAR(30))  AS [ID 1: Issued By],
    CAST(NULL AS VARCHAR(10))  AS [ID 2: Type],
    CAST(NULL AS VARCHAR(20))  AS [ID 2: Number],
    CAST(NULL AS DATE)         AS [ID 2: IssueDate],
    CAST(NULL AS VARCHAR(5))   AS [ID 2: IssueCountry],
    CAST(NULL AS DATE)         AS [ID 2: ExpiryDate],
    CAST(NULL AS VARCHAR(30))  AS [ID 2: Issued By],
    CAST(NULL AS VARCHAR(10))  AS [ID 3: Type],
    CAST(NULL AS VARCHAR(20))  AS [ID 3: Number],
    CAST(NULL AS DATE)         AS [ID 3: IssueDate],
    CAST(NULL AS VARCHAR(5))   AS [ID 3: IssueCountry],
    CAST(NULL AS DATE)         AS [ID 3: ExpiryDate],
    CAST(NULL AS VARCHAR(30))  AS [ID 3: Issued By],

    CASE WHEN c.Mobile1 IS NOT NULL THEN 'MOBILE' END             AS [Contact 1: Type],
    c.Mobile1                                                     AS [Contact 1: Value],
    CASE WHEN c.Email1 IS NOT NULL THEN 'EMAIL' END               AS [Contact 2: Type],
    c.Email1                                                      AS [Contact 2: Value],

    CAST(NULL AS VARCHAR(50))  AS [Employment: Trade Name],
    c.NID                      AS [Employment: TIN],   -- ORO stores the member TIN in the CIF NID field
    CAST(NULL AS VARCHAR(20))  AS [Employment: Phone Number],
    CAST(NULL AS VARCHAR(20))  AS [Employment:  PSIC],
    CAST(NULL AS DECIMAL(15,2)) AS [Employment: GrossIncome],
    CAST(NULL AS VARCHAR(1))   AS [Employment: Annual/Monthly Indicator],
    CAST(NULL AS VARCHAR(3))   AS [Employment: Currency],
    CAST(NULL AS VARCHAR(20))  AS [Employment: OccupationStatus],
    CAST(NULL AS DATE)         AS [Employment: DateHiredFrom],
    CAST(NULL AS DATE)         AS [Employment: DateHiredTo],
    CAST(NULL AS VARCHAR(30))  AS [Employment: Occupation],

    -- --------------------------------------------------------------------------
    -- Classification inputs (drive Type / Kind / MIGS / Active in the registry app)
    -- The four columns below are NOT in the CIC schema. Replace each CAST(NULL...)
    -- with a join to your Share Capital / Regular Savings sub-ledgers, e.g.:
    --     shb.Balance                         AS [Share Capital Balance]
    --     svb.Balance                         AS [Savings Balance]
    --     shx.LastTxnDate                     AS [Last Share Transaction Date]
    --     svx.LastTxnDate                     AS [Last Savings Transaction Date]
    -- (Balances "as of" the month you are exporting.)
    -- --------------------------------------------------------------------------
    CAST(NULL AS DECIMAL(15,2)) AS [Share Capital Balance],          -- TODO: join shares sub-ledger
    CAST(NULL AS DECIMAL(15,2)) AS [Savings Balance],                -- TODO: join regular-savings sub-ledger
    CAST(NULL AS DATE)          AS [Last Share Transaction Date],    -- TODO: latest share-account transaction
    CAST(NULL AS DATE)          AS [Last Savings Transaction Date]   -- TODO: latest savings-account transaction
INTO #IDIndividual
FROM T_CIF c
LEFT JOIN T_ADDRESS addr1 ON addr1.CID = c.CID AND addr1.AddressType = '001'
LEFT JOIN T_ADDRESS addr2 ON addr2.CID = c.CID AND addr2.AddressType = '002'
LEFT JOIN T_RELCID  rc    ON rc.CID = c.CID AND rc.Type = '061'
LEFT JOIN T_CIF     sp    ON sp.CID = rc.RelatedCID
LEFT JOIN T_BRPARMS br    ON br.Br = c.BR
WHERE c.Type = '001';
GO

/* ============================================================================
   PART B  -  Credit / contract staging  (from "SQLQuery8 query for CIC CI FINAL.sql")
   ============================================================================ */
SELECT
    h.CID, l.Acc, l.AppType, l.PrType, l.FreqType, l.CcyType,
    l.LnCode1, l.AccStatus,
    h.LnOpenDate, h.LnMatDate, h.CloseDate,
    h.LnPrincipalAmt, h.LnInstNo, h.LnLateDaysNo,
    br.BrName AS BranchName          -- member's branch, resolved the same way as the ID report
INTO #LoanBase
FROM T_LNACC l
JOIN T_LNHIST h    ON h.Acc = l.Acc
LEFT JOIN T_CIF cif ON cif.CID = h.CID
LEFT JOIN T_BRPARMS br ON br.Br = cif.BR;

SELECT
    i.Acc,
    MIN(i.DueDate)                                              AS FirstPaymentDate,
    MAX(CASE WHEN i.Status = 'P' THEN i.PaidDate END)           AS LastPaymentDate,
    MAX(CASE WHEN i.Status = 'P' THEN i.PriAmt + i.IntAmt END)  AS LastPaymentAmt,
    SUM(CASE WHEN i.Status = 'P' THEN 0 ELSE 1 END)             AS OutstandingPaymentsNo,
    SUM(CASE WHEN i.Status = 'P' THEN 0 ELSE i.PriAmt + i.IntAmt END) AS OutstandingBalance,
    SUM(CASE WHEN i.Status <> 'P' AND i.DueDate < CAST(GETDATE() AS DATE) THEN 1 ELSE 0 END) AS OverduePaymentsNo,
    SUM(CASE WHEN i.Status <> 'P' AND i.DueDate < CAST(GETDATE() AS DATE) THEN i.PriAmt + i.IntAmt ELSE 0 END) AS OverdueAmount,
    -- installments that fell due in the trailing 12 months and are still unpaid
    -- (drives the "loan paid current for 1 year" part of the Active/Inactive rule)
    SUM(CASE WHEN i.Status <> 'P'
              AND i.DueDate <  CAST(GETDATE() AS DATE)
              AND i.DueDate >= DATEADD(MONTH, -12, CAST(GETDATE() AS DATE))
             THEN 1 ELSE 0 END) AS OverdueLast12Mo
INTO #InstAgg
FROM T_LNINST i
GROUP BY i.Acc;

SELECT i.Acc, i.PriAmt + i.IntAmt AS NextAmt, i.DueDate,
       ROW_NUMBER() OVER (PARTITION BY i.Acc ORDER BY i.DueDate ASC) AS rn
INTO #NextInst
FROM T_LNINST i
WHERE i.Status <> 'P';

SELECT
    ra.Acc, ra.CID AS GuarantorCID, g.Name1, g.Name2,
    ROW_NUMBER() OVER (PARTITION BY ra.Acc ORDER BY ra.CID) AS GuarNo
INTO #Guarantors
FROM T_RELACC ra
JOIN T_CIF g ON g.CID = ra.CID
WHERE ra.Type = '030';

SELECT
    ra.Acc, ra.CID AS LinkedCID, ra.Type, l.Name1, l.Name2,
    ROW_NUMBER() OVER (PARTITION BY ra.Acc ORDER BY ra.CID) AS LinkNo
INTO #LinkedSubs
FROM T_RELACC ra
JOIN T_CIF l ON l.CID = ra.CID
WHERE ra.Type IN ('011','013','014');

SELECT Acc, MAX(Code) AS Code
INTO #Purpose
FROM T_LNRSN
GROUP BY Acc;
GO

SELECT
    'CI'                                                AS [Record Type],
    'CO014030'                                          AS [Provider Code],
    b.BranchName                                        AS [Branch Code],
    CONVERT(VARCHAR(8), CAST(GETDATE() AS DATE), 112)   AS [Contract Reference Date],
    b.CID                                               AS [Provider Subject No],
    'B'                                                 AS [Role],
    b.Acc                                               AS [Provider Contract No],
    b.LnCode1                                           AS [Contract Type],
    CASE
        WHEN b.AccStatus IN ('400','401') THEN 'PE'
        WHEN b.AccStatus IN ('411','450') THEN 'AC'
        WHEN b.AccStatus = '490'          THEN 'PM'
        WHEN b.AccStatus = '499'          THEN 'CL'
        WHEN b.AccStatus IN ('4C1','4C2') THEN 'CA'
        ELSE b.AccStatus
    END                                                 AS [Contract Phase],
    CAST(NULL AS VARCHAR(5))                            AS [Contract Status],
    ISNULL(ccy.Code, b.CcyType)                         AS [Currency],
    b.CcyType                                           AS [Original Currency],
    b.LnOpenDate                                        AS [Contract Start Date],
    CAST(NULL AS DATE)                                  AS [Contract Request Date],
    b.LnMatDate                                         AS [Contract End Planned Date],
    b.CloseDate                                         AS [Contract End Actual Date],
    ia.LastPaymentDate                                  AS [Last Payment Date],
    0                                                   AS [Reorganized Credit Code],
    0                                                   AS [Board Resolution flag],
    b.LnPrincipalAmt                                    AS [Financed Amount],
    b.LnInstNo                                          AS [Installments Number],
    'NA'                                                AS [Transaction Type / Sub-facility],
    p.Code                                              AS [Purpose of credit],
    CASE b.FreqType
        WHEN '000' THEN 'NEVER' WHEN '001' THEN 'A' WHEN '002' THEN 'SA'
        WHEN '004' THEN 'Q'     WHEN '012' THEN 'M' WHEN '052' THEN 'W'
        WHEN '360' THEN 'D'     ELSE b.FreqType
    END                                                 AS [Payment Periodicity],
    CAST(NULL AS VARCHAR(5))                            AS [Payment Method],
    ni.NextAmt                                          AS [Monthly Payment Amount],
    ia.FirstPaymentDate                                 AS [First Payment Date],
    ia.LastPaymentAmt                                   AS [Last payment amount],
    ni.DueDate                                          AS [Next Payment Date],
    ni.NextAmt                                          AS [Next Payment],
    ia.OutstandingPaymentsNo                            AS [Outstanding Payments Number],
    ia.OutstandingBalance                               AS [Outstanding Balance],
    ia.OverduePaymentsNo                                AS [Overdue Payments Number],
    ia.OverdueAmount                                    AS [Overdue Payments Amount],
    ISNULL(ia.OverdueLast12Mo, 0)                       AS [Overdue Installments Last 12 Months],
    b.LnLateDaysNo                                      AS [Overdue Days],
    CAST(NULL AS VARCHAR(5))   AS [Good Type],
    CAST(NULL AS VARCHAR(30))  AS [Good Value],
    CAST(NULL AS VARCHAR(1))   AS [New/Used Code],
    CAST(NULL AS VARCHAR(30))  AS [Good Brand],
    CAST(NULL AS DATE)         AS [Manufacturing Date],
    CAST(NULL AS VARCHAR(30))  AS [Registration number],
    g1.GuarantorCID                                            AS [Provider Guarantee No 1],
    g1.GuarantorCID                                            AS [Provider Subject No (Guarantor) 1],
    LTRIM(RTRIM(ISNULL(g1.Name2,'') + ' ' + ISNULL(g1.Name1,''))) AS [Guarantor Name 1],
    g2.GuarantorCID                                            AS [Provider Guarantee No 2],
    g2.GuarantorCID                                            AS [Provider Subject No (Guarantor) 2],
    LTRIM(RTRIM(ISNULL(g2.Name2,'') + ' ' + ISNULL(g2.Name1,''))) AS [Guarantor Name 2],
    g3.GuarantorCID                                            AS [Provider Guarantee No 3],
    g3.GuarantorCID                                            AS [Provider Subject No (Guarantor) 3],
    LTRIM(RTRIM(ISNULL(g3.Name2,'') + ' ' + ISNULL(g3.Name1,''))) AS [Guarantor Name 3],
    g4.GuarantorCID                                            AS [Provider Guarantee No 4],
    g4.GuarantorCID                                            AS [Provider Subject No (Guarantor) 4],
    LTRIM(RTRIM(ISNULL(g4.Name2,'') + ' ' + ISNULL(g4.Name1,''))) AS [Guarantor Name 4],
    g5.GuarantorCID                                            AS [Provider Guarantee No 5],
    g5.GuarantorCID                                            AS [Provider Subject No (Guarantor) 5],
    LTRIM(RTRIM(ISNULL(g5.Name2,'') + ' ' + ISNULL(g5.Name1,''))) AS [Guarantor Name 5],
    g6.GuarantorCID                                            AS [Provider Guarantee No 6],
    g6.GuarantorCID                                            AS [Provider Subject No (Guarantor) 6],
    LTRIM(RTRIM(ISNULL(g6.Name2,'') + ' ' + ISNULL(g6.Name1,''))) AS [Guarantor Name 6],
    ls1.LinkedCID AS [Provider Subject No (Linked Subject 1)], ls1.Type AS [Linked Subject 1 Role],
    LTRIM(RTRIM(ISNULL(ls1.Name2,'') + ' ' + ISNULL(ls1.Name1,''))) AS [Name of the Linked Subject 1],
    ls2.LinkedCID AS [Provider Subject No (Linked Subject 2)], ls2.Type AS [Linked Subject 2 Role],
    LTRIM(RTRIM(ISNULL(ls2.Name2,'') + ' ' + ISNULL(ls2.Name1,''))) AS [Name of the Linked Subject 2],
    ls3.LinkedCID AS [Provider Subject No (Linked Subject 3)], ls3.Type AS [Linked Subject 3 Role],
    LTRIM(RTRIM(ISNULL(ls3.Name2,'') + ' ' + ISNULL(ls3.Name1,''))) AS [Name of the Linked Subject 3],
    ls4.LinkedCID AS [Provider Subject No (Linked Subject 4)], ls4.Type AS [Linked Subject 4 Role],
    LTRIM(RTRIM(ISNULL(ls4.Name2,'') + ' ' + ISNULL(ls4.Name1,''))) AS [Name of the Linked Subject 4],
    ls5.LinkedCID AS [Provider Subject No (Linked Subject 5)], ls5.Type AS [Linked Subject 5 Role],
    LTRIM(RTRIM(ISNULL(ls5.Name2,'') + ' ' + ISNULL(ls5.Name1,''))) AS [Name of the Linked Subject 5],
    ls6.LinkedCID AS [Provider Subject No (Linked Subject 6)], ls6.Type AS [Linked Subject 6 Role],
    LTRIM(RTRIM(ISNULL(ls6.Name2,'') + ' ' + ISNULL(ls6.Name1,''))) AS [Name of the Linked Subject 6]
INTO #CIReport
FROM #LoanBase b
LEFT JOIN CCY ccy            ON ccy.Code = b.CcyType
LEFT JOIN #InstAgg ia         ON ia.Acc = b.Acc
LEFT JOIN #NextInst ni        ON ni.Acc = b.Acc AND ni.rn = 1
LEFT JOIN #Purpose p          ON p.Acc = b.Acc
LEFT JOIN #Guarantors g1      ON g1.Acc = b.Acc AND g1.GuarNo = 1
LEFT JOIN #Guarantors g2      ON g2.Acc = b.Acc AND g2.GuarNo = 2
LEFT JOIN #Guarantors g3      ON g3.Acc = b.Acc AND g3.GuarNo = 3
LEFT JOIN #Guarantors g4      ON g4.Acc = b.Acc AND g4.GuarNo = 4
LEFT JOIN #Guarantors g5      ON g5.Acc = b.Acc AND g5.GuarNo = 5
LEFT JOIN #Guarantors g6      ON g6.Acc = b.Acc AND g6.GuarNo = 6
LEFT JOIN #LinkedSubs ls1     ON ls1.Acc = b.Acc AND ls1.LinkNo = 1
LEFT JOIN #LinkedSubs ls2     ON ls2.Acc = b.Acc AND ls2.LinkNo = 2
LEFT JOIN #LinkedSubs ls3     ON ls3.Acc = b.Acc AND ls3.LinkNo = 3
LEFT JOIN #LinkedSubs ls4     ON ls4.Acc = b.Acc AND ls4.LinkNo = 4
LEFT JOIN #LinkedSubs ls5     ON ls5.Acc = b.Acc AND ls5.LinkNo = 5
LEFT JOIN #LinkedSubs ls6     ON ls6.Acc = b.Acc AND ls6.LinkNo = 6;
GO

/* ============================================================================
   PART C  -  #LoanAggByCID   (per-member loan roll-up: new in the merged script)
   ============================================================================ */
SELECT
    b.CID,
    COUNT(*)                                              AS LoanCount,
    SUM(b.LnPrincipalAmt)                                 AS TotalFinancedAmount,
    SUM(ISNULL(ia.OutstandingBalance,0))                  AS TotalOutstandingBalance,
    SUM(ISNULL(ia.OverdueAmount,0))                       AS TotalOverdueAmount,
    SUM(ISNULL(ia.OverduePaymentsNo,0))                   AS TotalOverduePaymentsNo,
    SUM(ISNULL(ia.OverdueLast12Mo,0))                     AS OverdueInstallmentsLast12Mo,
    MAX(b.LnLateDaysNo)                                   AS MaxOverdueDays,
    SUM(CASE WHEN ISNULL(b.LnLateDaysNo,0) > 0 THEN 1 ELSE 0 END) AS DelinquentLoanCount,
    MIN(b.LnOpenDate)                                     AS EarliestLoanOpenDate,
    MAX(b.LnMatDate)                                      AS LatestLoanMaturityDate
INTO #LoanAggByCID
FROM #LoanBase b
LEFT JOIN #InstAgg ia ON ia.Acc = b.Acc
GROUP BY b.CID;
GO

/* ============================================================================
   RESULT SET 1  -  "Members"   (export -> members.xlsx)
   ============================================================================ */
SELECT
    i.*,
    ISNULL(la.LoanCount, 0)                 AS [Loan Count],
    ISNULL(la.TotalFinancedAmount, 0)       AS [Total Financed Amount],
    ISNULL(la.TotalOutstandingBalance, 0)   AS [Total Outstanding Balance],
    ISNULL(la.TotalOverdueAmount, 0)        AS [Total Overdue Amount],
    ISNULL(la.OverdueInstallmentsLast12Mo, 0) AS [Overdue Installments Last 12 Months],
    la.MaxOverdueDays                       AS [Max Overdue Days],
    ISNULL(la.DelinquentLoanCount, 0)       AS [Delinquent Loan Count],
    la.EarliestLoanOpenDate                 AS [Earliest Loan Open Date],
    la.LatestLoanMaturityDate               AS [Latest Loan Maturity Date]
FROM #IDIndividual i
LEFT JOIN #LoanAggByCID la ON la.CID = i.[Provider Subject No]
ORDER BY i.[Last Name], i.[First Name];

/* ============================================================================
   RESULT SET 2  -  "Loans"   (export -> loans.xlsx)
   ============================================================================ */
SELECT * FROM #CIReport
ORDER BY [Provider Subject No], [Provider Contract No];
